# Coffee Chat

A Laravel application that facilitates periodic matching of Slack channel members for informal chats.

## Features

- Automatic matching of members every 90 days
- Slack integration for member synchronization and communication
- Smart matching algorithm preventing repeat pairings
- Automated DM creation for matched pairs

## Setup

1. Install dependencies:
```bash
composer install
```

2. Configure environment variables:
```env
SLACK_BOT_TOKEN=your-bot-token
SLACK_CHANNEL_ID=your-channel-id
```

3. Run migrations:
```bash
php artisan migrate
```

4. Schedule the matching command in your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## API Routes

- `GET /api/members` - List all members
- `POST /api/members` - Register a new member
- `GET /api/matches` - View all matches
- `PATCH /api/matches/{match}/met` - Mark a match as completed

## Commands

- `php artisan app:match-slack-users` - Manually trigger the matching process

## Required Slack Bot Permissions

- `channels:read`
- `chat:write`
- `im:write`
- `users:read`
- `users:read.email`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Submit a pull request
