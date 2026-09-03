# Deploy FrankenPHP + Redis + PgBouncer — penerbitpcp.com

2vCPU / 2GB RAM, target >1000 concurrent.

## Stack

| Component | Port | Purpose |
|-----------|------|---------|
| FrankenPHP | :80/:443 | Web server (non-worker mode) |
| PgBouncer | :6439 | DB connection pool (transaction mode, 20→PG) |
| Redis | :6379 | Cache + Session (256MB max, LRU eviction) |
| PostgreSQL | :5432 | Database |
| Queue Worker | — | `queue:work` via systemd |

## Service Management

```bash
# Status
sudo systemctl status penerbitpcp penerbitpcp-worker redis-server pgbouncer postgresql

# Restart one
sudo systemctl restart penerbitpcp

# Logs
journalctl -u penerbitpcp -f
journalctl -u penerbitpcp-worker -f
```

## Install (sekali)

```bash
# FrankenPHP
curl -s https://frankenphp.dev/install.sh | sh
sudo mv frankenphp /usr/local/bin/

# Redis
sudo apt install -y redis-server
sudo systemctl enable --now redis-server

# PHP config (memory_limit + OPcache)
sudo mkdir -p /etc/frankenphp/php.d
sudo tee /etc/frankenphp/php.d/99-custom.ini << 'INI'
memory_limit=256M
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
INI

# Network tuning
sudo tee /etc/sysctl.d/99-penerbitpcp.conf << 'SYSCTL'
net.core.somaxconn = 4096
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_keepalive_time = 300
net.ipv4.tcp_keepalive_intvl = 15
net.ipv4.tcp_keepalive_probes = 5
net.ipv4.ip_local_port_range = 1024 65535
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
SYSCTL
sudo sysctl --system

# .env additions
# CACHE_STORE=redis
# SESSION_DRIVER=redis
# DB_PORT=6439 (PgBouncer)
# TRUSTED_PROXIES=103.21.244.0/22,... (Cloudflare IPs)

# Config cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Deploy (tiap push ke penerbit-pcp)

`.github/workflows/deploy.yml` otomatis:
1. `git reset --hard origin/penerbit-pcp`
2. `composer install --no-dev`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `php artisan config:cache && route:cache && view:cache && event:cache`
6. `systemctl restart penerbitpcp`

Queue worker otomatis restart setelah deploy via `Restart=always`.

## .env Production

```ini
APP_ENV=production
APP_DEBUG=false
CACHE_STORE=redis
SESSION_DRIVER=database  → ganti ke redis
QUEUE_CONNECTION=database
LOG_CHANNEL=stack
LOG_STACK=daily
DB_PORT=6439
TRUSTED_PROXIES=103.21.244.0/22,103.22.200.0/22,...
```

## Verification

```bash
curl -s -o /dev/null -w "HTTP %{http_code}" https://penerbitpcp.com
redis-cli ping
psql -h 127.0.0.1 -p 6439 -U toko_buku -d toko_buku -c "SHOW POOLS;"
```
