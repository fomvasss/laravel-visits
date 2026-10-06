# When to use this package

This is not a GA4 replacement. GA4 is free at any scale, runs on global real-time infrastructure, has ML predictions and deep Google Ads integration — a self-hosted package does not compete with that. Use this package when you want tracking that lives inside your app, next to your data:

| | This package | GA4 | Plausible / Fathom | Matomo (self-hosted) |
|---|---|---|---|---|
| Data location | Your database | Google's servers | Vendor's servers (hosted) | Your database |
| Events attached to your own models (`Order`, `Lead`) through an Eloquent relation | Yes (`eventable`) | No — separate system, joined manually | No | No |
| Query visits together with your app's data (one JOIN, no export) | Yes | No | No | No |
| Cost at high volume | Your infrastructure | Free | Paid per page-view tier | Your infrastructure |
| Real-time global scale, ML, ad-platform sync | No | Yes | No | Partial |

In practice: "which `orders` came from a Facebook campaign, joined with my own `orders` table" is what this package exists for. "How is our traffic trending against industry benchmarks, with nothing to run" is GA4's job. Running both side by side is fine — they answer different questions.

The cost is load on your own stack: one queued job per tracked request, rows in your database, and a scheduler for rollups and retention.
