use ext_php_rs::prelude::*;
use ext_php_rs::{boxed::ZBox, types::ZendHashTable};

#[php_function]
pub fn parse_http(buffer: &str) -> Option<ZBox<ZendHashTable>> {
    let mut headers = [httparse::EMPTY_HEADER; 64];
    let mut req = httparse::Request::new(&mut headers);

    if let Ok(httparse::Status::Complete(_)) = req.parse(buffer.as_bytes()) {
        let mut result = ZendHashTable::new();

        let _ = result.insert("method", req.method?);
        let _ = result.insert("path", req.path?);

        let mut header_table = ZendHashTable::new();
        for h in req.headers {
            if !h.name.is_empty() {
                if let Ok(val) = std::str::from_utf8(h.value) {
                    let key = h.name.to_lowercase();
                    let _ = header_table.insert(key, val);
                }
            }
        }

        let _ = result.insert("headers", header_table);
        return Some(result);
    }
    None
}

#[php_module]
pub fn module(module: ModuleBuilder) -> ModuleBuilder {
    module.function(wrap_function!(parse_http))
}
