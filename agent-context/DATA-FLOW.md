# Data Flow Diagrams

> **Version:** 1.6.0 | **Last updated:** 2026-02-24

## Order Processing Flow

```mermaid
flowchart TD
    A[woocommerce_order_status_changed] --> B{order exists?}
    B -->|No| Z[return]
    B -->|Yes| C{order_has_telegram_product?<br>simple product + _telegram_channel_ids}
    C -->|No| Z
    C -->|Yes| D{new_status in<br>processing, completed?}
    D -->|No| Z
    D -->|Yes| E{old_status = processing<br>AND new_status = completed?}
    E -->|Yes| Z[skip - already processed]
    E -->|No| F{wctlgm_require_activation_flow?}

    F -->|Yes| G{_activation_code<br>already exists?}
    G -->|Yes| Z
    G -->|No| H[generate_activation_code<br>8-char alphanumeric]
    H --> I[Save _activation_code to order]

    F -->|No| J{existing invite<br>links on order?}
    J -->|Yes| Z
    J -->|No| K[Subscriptions_Handler::get_channel_invites]
    K --> L[Store _channel_invite_ meta per channel]
    L --> M[Fire wc_wctlgm_invite_links_generated action]
    M --> N[Schedule wctlgm_send_invite_links_email<br>5-second delay via Action Scheduler]
```

## Telegram Webhook Processing Flow

```mermaid
flowchart TD
    A[POST /wp-json/wctlgm/v1/telegram-bot/] --> B{X-Telegram-Bot-Api-Secret-Token<br>matches wctlgm_secret_token?}
    B -->|No| C[403 Forbidden]
    B -->|Yes| D[Bot_Interaction_Handler::process_telegram_request]

    D --> E{Request type?}

    E -->|chat_join_request| F[process_join_request]
    F --> G[Subscriptions_Handler::is_join_request_valid]
    G -->|Valid| H[approve_join_request + revoke_invite_link]
    G -->|Invalid| I[deny_join_request]

    E -->|edited_channel_post<br>or edited_message| J{fetch initiated<br>from settings?}
    J -->|Yes| K[Save chat_id to transient<br>wctlgm_channel_id_temp_store]
    J -->|No| L[Skip - not initiated from settings]

    E -->|message| M{chat type = private?}
    M -->|No| N[return none]
    M -->|Yes| O[extract_command_and_args]
    O --> P{command?}
    P -->|/start| Q[handle_start_command]
    P -->|/activate| R[handle_activation_command]
    P -->|/help| S[handle_help_command]
    P -->|other| T[Invalid command response]

    E -->|other| N
```

## Activation Flow

```mermaid
sequenceDiagram
    participant Customer
    participant WooCommerce
    participant Plugin
    participant TelegramAPI
    participant Bot

    Customer->>WooCommerce: Places order
    WooCommerce->>Plugin: order_status → processing/completed
    Plugin->>Plugin: generate 8-char activation code
    Plugin->>Plugin: Save _activation_code to order

    WooCommerce->>Customer: Processing/Completed email<br>(includes activation code + bot link)

    Customer->>Bot: Click deep link: t.me/bot?start=CODE<br>or /activate CODE

    Bot->>Plugin: Webhook POST (message)
    Plugin->>Plugin: extract_command_and_args
    Plugin->>Plugin: handle_start_command or handle_activation_command

    Plugin->>Plugin: Subscriptions_Handler::process_activation_code
    Note over Plugin: Find order by _activation_code<br>Validate status (processing/completed)<br>Store _telegram_user_id<br>Delete _activation_code

    Plugin->>TelegramAPI: createChatInviteLink (creates_join_request=true)
    TelegramAPI-->>Plugin: invite_link
    Plugin->>Plugin: Store _channel_invite_{channel_id}

    Plugin->>Bot: Send invite link(s) to user
    Plugin->>Plugin: Schedule wctlgm_send_activation_email

    Customer->>TelegramAPI: Click invite → join request
    TelegramAPI->>Plugin: Webhook POST (chat_join_request)

    Plugin->>Plugin: is_join_request_valid<br>_telegram_user_id matches?
    Plugin->>TelegramAPI: approveChatJoinRequest
    Plugin->>TelegramAPI: revokeChatInviteLink
```

## Direct Invite Flow (No Activation)

```mermaid
sequenceDiagram
    participant Customer
    participant WooCommerce
    participant Plugin
    participant TelegramAPI

    Customer->>WooCommerce: Places order
    WooCommerce->>Plugin: order_status → processing/completed

    Plugin->>Plugin: Subscriptions_Handler::get_channel_invites
    Plugin->>TelegramAPI: createChatInviteLink (creates_join_request=true)
    TelegramAPI-->>Plugin: invite_link

    Plugin->>Plugin: Store _channel_invite_{channel_id}
    Plugin->>Plugin: Fire wc_wctlgm_invite_links_generated
    Plugin->>Plugin: Schedule email (5s delay)

    Plugin->>Customer: Invite Links Email with one-time links

    Customer->>TelegramAPI: Click invite → join request
    TelegramAPI->>Plugin: Webhook POST (chat_join_request)

    Plugin->>Plugin: find_order_by_invite_link(invite_link, chat_id)
    Note over Plugin: Order found, status valid<br>No _telegram_user_id yet<br>→ Capture user ID, approve

    Plugin->>TelegramAPI: approveChatJoinRequest
    Plugin->>TelegramAPI: revokeChatInviteLink
```

## Join Request Validation Flow

```mermaid
flowchart TD
    A[chat_join_request received] --> B[find_order_by_invite_link<br>invite_link + chat_id]
    B --> C{Order found?}

    C -->|No| D{allow_external_invites?}
    D -->|Yes| E[Approve - external invite]
    D -->|No| F[Deny - no matching order]

    C -->|Yes| G{Order status in<br>processing, completed?}
    G -->|No| F2[Deny - invalid order status]
    G -->|Yes| H{require_activation_flow?}

    H -->|Yes| I{_telegram_user_id<br>matches requesting user?}
    I -->|Yes| J[Approve]
    I -->|No| K[Deny - user mismatch]

    H -->|No| L{_telegram_user_id<br>already set?}
    L -->|No| M[Set _telegram_user_id → Approve]
    L -->|Yes| N{Same user?}
    N -->|Yes| O[Approve]
    N -->|No| P[Deny - attempted overwrite<br>+ log warning]
```

## Channel ID Fetch Flow (Settings Page)

```mermaid
sequenceDiagram
    participant Admin
    participant Settings as Settings Page (JS)
    participant AJAX
    participant Transients as WordPress Transients
    participant Webhook as Telegram Webhook

    Admin->>Settings: Click "Get ID" button
    Settings->>AJAX: check_and_set_channel_id

    AJAX->>Transients: get wctlgm_channel_id_temp_store
    alt No channel ID stored
        AJAX->>Transients: set wctlgm_telegram_fetch_channel_id_active = true (1hr)
        AJAX-->>Settings: Error: "Post a message and edit it"
        Settings-->>Admin: Display instruction message

        Note over Admin: Admin goes to Telegram channel<br>Posts a message, then edits it

        Webhook->>Webhook: Receive edited_message/edited_channel_post
        Webhook->>Transients: Check wctlgm_telegram_fetch_channel_id_active
        Note over Webhook: Active = true → save channel ID
        Webhook->>Transients: set wctlgm_channel_id_temp_store = chat_id (1hr)
        Webhook->>Transients: delete wctlgm_telegram_fetch_channel_id_active

        Admin->>Settings: Click "Get ID" again
        Settings->>AJAX: check_and_set_channel_id
        AJAX->>Transients: get wctlgm_channel_id_temp_store
    end

    AJAX->>Transients: delete wctlgm_channel_id_temp_store
    AJAX-->>Settings: Success: {channel_id}
    Settings-->>Admin: Populate channel ID field
```
