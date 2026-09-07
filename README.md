# phphony

This is my first real PHP project.
Of course I've done PHP before, but it was forced by school and... I used AI 100% to do the assignments so it doesn't count 😝

Here, I'm trying to build an HTTP TCP Server using native PHP with some help (ReactPHP) to make it async/non-blocking.

### Overview
* **Parses Raw HTTP:** Manually reads TCP stream buffers to extract request methods, headers, query parameters, and JSON bodies.
* **Serves Static Assets:** Safely delivers files (`.html`, `.css`, `.js`, etc.) from a `public/` folder with MIME type detection.
* **Handles Basic Routing:** Matches endpoints like `GET /about` or `POST /api` and formats raw HTTP responses.
* **Logs Traffic:** Dumps incoming request data directly to the terminal for debugging.

### Quick Start
```bash
# Install deps and run
composer install
php src/index.php
```
