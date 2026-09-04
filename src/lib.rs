use std::collections::HashMap;

use ext_php_rs::prelude::*;
use ext_php_rs::{
    boxed::ZBox,
    types::{ZendHashTable, Zval},
};

struct Request<'a> {
    method: &'a str,
    path: &'a str,
    headers: Vec<(String, &'a str)>,
    offset: usize,
}

fn parse_internal(buffer: &str) -> Option<Request<'_>> {
    let mut headers = [httparse::EMPTY_HEADER; 64];
    let mut req = httparse::Request::new(&mut headers);

    if let Ok(httparse::Status::Complete(offset)) = req.parse(buffer.as_bytes()) {
        let mut headers_vec = Vec::new();

        for h in req.headers {
            if h.name.is_empty() {
                break;
            }

            if let Ok(val) = std::str::from_utf8(h.value) {
                let key = h.name.to_lowercase();
                let _ = headers_vec.push((key, val));
            }
        }

        return Some(Request {
            method: req.method?,
            path: req.path?,
            headers: headers_vec,
            offset,
        });
    }
    None
}

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
    let req = parse_internal(buffer)?;

    let content_length: usize = req
        .headers
        .iter()
        .find(|(k, _)| k.eq_ignore_ascii_case("conteent-length"))
        .and_then(|(_, v)| v.parse().ok())
        .unwrap_or(0);

    let body_str = match buffer.get(req.offset..(req.offset + content_length)) {
        Some(body) => body,
        None => return None,
    };

    let (path, query_str) = req.path.split_once('?').unwrap_or((req.path, ""));

    let mut query_table = ZendHashTable::new();
    if !query_str.is_empty() {
        let parsed_query = form_urlencoded::parse(query_str.as_bytes());
        for (key, val) in parsed_query {
            let _ = query_table.insert(key.as_ref(), val.as_ref());
        }
    }

    let mut body_table = ZendHashTable::new();

    let content_type = req
        .headers
        .iter()
        .find(|(k, _)| k.eq_ignore_ascii_case("content-type"))
        .map(|(_, v)| *v)
        .unwrap_or("");

    if content_type.contains("application/json") {
        if let Ok(json_val) = serde_json::from_str::<serde_json::Value>(body_str) {
            if let serde_json::Value::Object(obj) = json_val {
                for (k, v) in obj {
                    let _ = body_table.insert(k.as_str(), json_to_zval(&v));
                }
            }
        }
    }

    let mut headers_table = ZendHashTable::new();
    for (key, val) in req.headers {
        let _ = headers_table.insert(key, val);
    }

    let mut result = ZendHashTable::new();
    let _ = result.insert("method", req.method);
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
