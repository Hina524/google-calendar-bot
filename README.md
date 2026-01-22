# Google Calendar Bot

Google Calendar の予定追加を Discord に通知する Bot です。

複数人でカレンダーを共有している時の「予定追加したじゃん！」「見てないよ！」「いや見てって言ったじゃん！」「わからないって！」みたいな事故を防ぐために作りました。

## 機能

- Google Calendar API の Push 通知で予定変更をリアルタイム検知
- 予定が追加されると Discord Webhook で通知
- 複数ユーザー対応

## 技術スタック

- PHP 8.4 / Laravel 12
- SQLite
- Docker (Alpine + PHP-FPM + Nginx)
- NixOS モジュール

## デプロイ

### NixOS

```nix
{
  services.calendar-bot = {
    enable = true;
    environmentFile = config.sops.templates."calendar-bot-env".path;
  };
}
```

### 環境変数

| 変数名 | 説明 |
|--------|------|
| `APP_KEY` | Laravel アプリケーションキー |
| `GOOGLE_CLIENT_ID` | Google OAuth クライアント ID |
| `GOOGLE_CLIENT_SECRET` | Google OAuth クライアントシークレット |
| `GOOGLE_REDIRECT_URI` | OAuth コールバック URL |
| `DISCORD_WEBHOOK_URL` | Discord Webhook URL |

## セットアップ

1. Google Cloud Console で OAuth クライアントを作成
2. Discord で Webhook を作成
3. デプロイして認証
