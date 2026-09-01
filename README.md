# phphony

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
