# Frontend Deployment Guide

## 1. Environment Variables

Vite uses **`.env`** files to inject variables at build time.  
All variables exposed to the browser **must** start with `VITE_`.

| File | Purpose | Committed? |
|------|---------|------------|
| `.env` | Local development | ❌ No — gitignored |
| `.env.local` | Local overrides (private) | ❌ No — gitignored |
| `.env.example` | Template for developers | ✅ Yes |
| `.env.production` | Production build template | ✅ Yes (replace URL before build) |
| `.env.production.local` | Secret prod overrides | ❌ No — gitignored |

## 2. Switch Between Local and Production API

### Option A — Development (default)
```bash
cd frontend
# Uses VITE_API_URL from .env
cp .env.example .env
npm run dev
```

### Option B — Production Build
```bash
cd frontend
# 1. Set production API URL
cp .env.production .env.production.local
# Edit .env.production.local with real URL

# 2. Build
npm run build

# 3. Preview locally
npm run preview
```

### Option C — One-off build with inline env
```bash
VITE_API_URL=https://luxurystay.example.com/api npm run build
```

## 3. Where to Place the API URL

```
┌─────────────────────────────────────────┐
│  React (Vite)      │  Laravel API       │
│  ─────────────     │  ───────────       │
│  VITE_API_URL      │  APP_URL           │
│  ↓                 │  ↓                 │
│  http://localhost  │  http://localhost  │
│     :8000/api      │     :8000/api      │
│  ↓                 │  ↓                 │
│  axios baseURL     │  routes/api.php    │
└─────────────────────────────────────────┘
```

* The frontend `VITE_API_URL` must end with **`/api`**.
* The `STORAGE_URL` is auto-derived by stripping `/api` for image assets.

## 4. Best Practices

1. **Never commit `.env`** — it is already in `.gitignore`.
2. **Use `.env.example`** as a public template with placeholder values.
3. **Keep secrets on the backend only** — anything prefixed with `VITE_` is visible in the browser bundle.
4. **Use `STORAGE_URL`** exported from `services/api.js` for image URLs instead of manual string manipulation.
5. **Production**: build on CI/CD (GitHub Actions / Vercel / Netlify) and inject `VITE_API_URL` via environment secrets.

## 5. Backend (Laravel) Configuration

Ensure your Laravel API is properly configured:

- Set APP_URL in `.env`:
  APP_URL=http://localhost:8000

- Run:
  php artisan storage:link

- Serve API:
  php artisan serve

For production:
- Set APP_URL to your domain
- Configure CORS to allow frontend domain


## 6. CORS Configuration

Make sure your Laravel API allows requests from your frontend:

config/cors.php:

'allowed_origins' => [
    'http://localhost:5173',
    'https://your-frontend-domain.com',
],

## 7. Image URLs

Images are served from Laravel storage:

- Example:
  http://localhost:8000/storage/images/file.jpg

Make sure:
- `php artisan storage:link` is executed
- Use STORAGE_URL helper in frontend


## 8. Deployment Options

Frontend:
- Vercel (recommended)
- Netlify

Backend:
- Render
- Railway
- VPS (DigitalOcean)

Example:
Frontend → https://luxurystay.vercel.app  
Backend → https://luxurystay-api.onrender.com

## 9. Troubleshooting

- API not working:
  → Check VITE_API_URL

- Images not loading:
  → Run php artisan storage:link

- CORS error
  → Check config/cors.php

- 500 error:
  → Check Laravel logs (storage/logs)