# Coffee Chat

Automated Slack member matching system built with Laravel.

## Setup

1. Environment setup:
```bash
cp .env.example .env
composer install
php artisan key:generate
```

2. Configure Slack:
```env
SLACK_BOT_TOKEN=xoxb-your-token
SLACK_CHANNEL_ID=C12345678
```

3. Database setup:
```bash
sail artisan migrate
```

4. Configure Xdebug:
```env
XDEBUG_MODE=debug
XDEBUG_CONFIG="client_host=host.docker.internal start_with_request=yes"
PHP_IDE_CONFIG="serverName=Docker"
```

5. Start containers:
```bash
sail up -d
```

## API Endpoints

### Slack Operations
- `GET /api/slack/members` - List channel members
- `POST /api/slack/sync` - Sync channel members to database

### Member Management
- `GET /api/members` - List all members
- `POST /api/members` - Create member
- `DELETE /api/members/{id}` - Remove member

### Match Operations
- `GET /api/matches` - View all matches
- `PATCH /api/matches/{match}/met` - Mark match as completed

## Automated Matching

Schedule runs quarterly via:
```bash
php artisan app:match-slack-users
```

## Required Bot Permissions
- `channels:read`
- `chat:write`
- `im:write`
- `users:read`
- `users:read.email`

## Development

Run tests:
```bash
sail artisan test
```

Debug with Xdebug in PHPStorm:
1. Set breakpoints
2. Start listening for PHP Debug connections
3. Use browser extension or query parameter ?XDEBUG_SESSION=1
