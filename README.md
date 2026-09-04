# phphony

This is my first real PHP project.

Of course I've done PHP before, but it was forced by school and... I used AI 100% to do the assignments so it doesn't count 😝

Here, I'm trying to build an HTTP TCP Server using native PHP with some help (ReactPHP) to make it async/non-blocking.

Also, I used Rust FFI for parsing (~1.55x faster), though its only for a trivial case and the complexity increased, but it's fun! For the sake of exploration!

### Native PHP
```bash
# Install deps and run
composer install
php src/index.php
```

### Rust FFI
```bash
# Install deps
composer install

# Install cargo-php
cargo install cargo-php --locked

# Build the release extension and run
cargo php install --release
php src/index.php
```
