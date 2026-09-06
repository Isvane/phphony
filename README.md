# phphony

This is my first real PHP project.

Of course I've done PHP before, but it was forced by school and... I used AI 100% to do the assignments so it doesn't count 😝

Here, I'm trying to build an HTTP TCP Server using native PHP with some help (ReactPHP) to make it async/non-blocking.

I actually experimented with a Rust FFI extension for parsing this at first. But once you factor in FFI and serialization overhead for a trivial case like this, it was only about 2.5% faster while adding a ton of build complexity. I ended up stripping it out to keep things pure PHP—way simpler to manage

### Quick Start
```bash
# Install deps and run
composer install
php src/index.php
```
