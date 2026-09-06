use std::collections::HashMap;

use ext_php_rs::prelude::*;
use ext_php_rs::{
    boxed::ZBox,
    types::{ZendHashTable, Zval},
};

fn json_to_zval(val: &serde_json::Value) -> Zval {
    let mut zv = Zval::new();

    match val {
        serde_json::Value::Null => {}
        serde_json::Value::Bool(b) => {
            zv.set_bool(*b);
        }
        serde_json::Value::Number(n) => {
            if let Some(i) = n.as_i64() {
                zv.set_long(i);
            } else if let Some(f) = n.as_f64() {
                zv.set_double(f);
            }
        }
        serde_json::Value::String(s) => {
            let _ = zv.set_string(s.as_str(), false);
        }
        serde_json::Value::Array(arr) => {
            let vec: Vec<Zval> = arr.iter().map(json_to_zval).collect();
            let _ = zv.set_array(vec);
        }
        serde_json::Value::Object(obj) => {
            let map: HashMap<String, Zval> = obj
                .iter()
                .map(|(k, v)| (k.clone(), json_to_zval(v)))
                .collect();
            let _ = zv.set_array(map);
        }
    }

    zv
}

#[php_function]
pub fn parse_http(buffer: &str) -> Option<ZBox<ZendHashTable>> {
    let mut headers = [httparse::EMPTY_HEADER; 32];
    let mut req = httparse::Request::new(&mut headers);

    let offset = match req.parse(buffer.as_bytes()) {
        Ok(httparse::Status::Complete(off)) => off,
        _ => return None,
    };

    let method = req.method?;
    let raw_path = req.path?;

    let mut content_length: usize = 0;
    let mut is_json = false;
    let mut headers_table = ZendHashTable::new();

    for h in req.headers {
        if h.name.is_empty() {
            break;
        }

        if let Ok(val) = std::str::from_utf8(h.value) {
            let lower_name = h.name.to_ascii_lowercase();

            if lower_name == "content-length" {
                content_length = val.parse().unwrap_or(0);
            } else if lower_name == "content-type" && val.contains("application/json") {
                is_json = true;
            }

            let _ = headers_table.insert(lower_name, val);
        }
    }

    if buffer.len() < offset + content_length {
        return None;
    }
    let body_str = &buffer[offset..offset + content_length];

    let (path, query_str) = raw_path.split_once('?').unwrap_or((raw_path, ""));

    let mut query_table = ZendHashTable::new();
    if !query_str.is_empty() {
        for (key, val) in form_urlencoded::parse(query_str.as_bytes()) {
            let _ = query_table.insert(key.as_ref(), val.as_ref());
        }
    }

    let mut body_table = ZendHashTable::new();
    if is_json && !body_str.is_empty() {
        if let Ok(serde_json::Value::Object(obj)) =
            serde_json::from_str::<serde_json::Value>(body_str)
        {
            for (k, v) in obj {
                let _ = body_table.insert(k.as_str(), json_to_zval(&v));
            }
        }
    }

    let mut result = ZendHashTable::new();
    let _ = result.insert("method", method);
    let _ = result.insert("path", if path.is_empty() { "/" } else { path });
    let _ = result.insert("headers", headers_table);
    let _ = result.insert("query", query_table);
    let _ = result.insert("body", body_table);

    Some(result)
}

#[php_module]
pub fn module(module: ModuleBuilder) -> ModuleBuilder {
    module.function(wrap_function!(parse_http))
}
