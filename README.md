# Coffee Chat Bot

A Laravel application that facilitates quarterly coffee chats by matching Slack channel members and tracking their interactions.

## Features
- Automatic member sync from Slack channel
- Random matching algorithm preventing repeat pairings
- Supports 2-3 person matches
- Automatic DM creation for matched groups
- Match history tracking in Google Sheets
- Slash command `/coffee-bot we met` for marking meetings complete

## Setup

1. Install dependencies:
   ```bash
   composer install
   ```

2. Configure environment:
   ```env
   SLACK_BOT_TOKEN=xoxb-your-token
   SLACK_CHANNEL_ID=C12345678
   SLACK_SIGNING_SECRET=your-signing-secret
   GOOGLE_SHEETS_ID=your-sheet-id
   ```

3. Set up Google Sheets:
   - Create service account and download credentials
   - Save as `storage/app/google/google-service-account.json`
   - Share sheet with service account email

4. Run migrations:
   ```bash
   sail artisan migrate
   ```

## Usage

### Manual Match Trigger
```bash
sail artisan app:match-slack-users
```

### Schedule Automatic Matching
Add to crontab:
```bash
sail artisan schedule:run
```

## Required Slack Bot Permissions
- `channels:read`
- `groups:read`
- `im:read`
- `mpim:read`
- `channels:manage`
- `groups:write`
- `im:write`
- `mpim:write`
- `chat-write`

## API Routes
- `GET /api/slack/members` - List channel members
- `POST /api/slack/sync` - Sync members
- `GET /api/matches` - View matches
- `DELETE /api/matches` - Clear all matches