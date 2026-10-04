# Next.js portal

The responsive portal supports Arabic, French, and English and calls the Laravel API directly. Configure `NEXT_PUBLIC_API_BASE_URL` and allow the portal's exact origin in the backend's `CORS_ALLOWED_ORIGINS`. Authentication tokens are held in memory and are not persisted in browser storage.

Run `npm ci`, then `npm run dev`. See the [project README](../README.md) and [real-world readiness limitations](../docs/REAL_WORLD_READINESS.md).
