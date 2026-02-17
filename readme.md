# Spark451 - Simple GET HTML

A lightweight WordPress plugin that lets you display values from URL query parameters directly in your posts, pages, or templates — with **shortcodes**.

Perfect for personalization, dynamic content, and quick campaign landing pages.

---

## 🚀 Installation

1. **Download & Upload**
    - Clone or download this repo as a ZIP.
    - Go to **WordPress Admin → Plugins → Add New → Upload Plugin**.
    - Select the ZIP and click **Install Now**, then **Activate**.

2. **Composer**

    Because this package is hosted on GitHub (not Packagist), you need to add the repository to your project's `composer.json` file:

    ```json
    "repositories": [
      {
        "type": "vcs",
        "url": "https://github.com/spark451inc/wp-spark451-simple-get-html"
      }
    ]
    ```

    Then run:
    ```bash
    composer require spark451inc/wp-spark451-simple-get-html
    ```

---

## 🧩 Usage

This plugin provides **two shortcodes** for displaying `$_GET` parameters:

---

### 1️⃣ Single Value – `[spark451_get_html_item]`

Outputs a single sanitized value from the query string.

#### Example:

**URL:**
https://example.com/landing-page/?bee=BuzzBuzz

**Shortcode:**
[spark451_get_html_item bee]

**Output:**
BuzzBuzz


✅ The value is fully sanitized and escaped before display.

---

### 2️⃣ Multiple Values – `[spark451_get_html_items]...[/spark451_get_html_items]`

Lets you use multiple keys and dynamically inject them into **custom HTML** using `{{placeholders}}`.

#### Example:

**URL:**
https://example.com/landing-page/?bee=BuzzBuzz&honey=Sweet


**Shortcode:**

```html
[spark451_get_html_items bee honey]
<div class="card">
  <h1>{{bee}}</h1>
  <p>{{honey}}</p>
</div>
[/spark451_get_html_items]

or

[spark451_get_html_items keys="bee,honey,left,right"]
<div class="card">
    <h1>{{bee}}</h1>
    <p>{{honey}}</p>
</div>
[/spark451_get_html_items]
```
**Output:**
```html
<div class="card">
  <h1>BuzzBuzz</h1>
  <p>Sweet</p>
</div>
```
- Missing query params are replaced with empty strings.
- You can add as many keys as you want in the opening tag.

---

## 🧠 Conditional Logic & Fallbacks
✅ Fallback for single values

If a query parameter doesn’t exist, you can define fallback text or HTML using the fallback attribute.

```html
[spark451_get_html_item key="first-name" fallback="<p>Welcome to Mississippi College!</p>"]
```

- If `?first-name=John` → outputs John
- If missing → outputs `<p>Welcome to Mississippi College!</p>`


You can also mark required keys inline with a ! or * prefix:

```html
[spark451_get_html_items !first_name guid fallback="<h2>Welcome!</h2>"]
  <h2>Hi {{first_name}}!</h2>
[/spark451_get_html_items]
```
## 🛡️ Security


- All values are sanitized with `sanitize_text_field()`.
- Output is escaped with `esc_html()` before display.
- Array-style query params are joined into a single space-separated string.
- No raw HTML from the query string is ever rendered (safe by default).

---

## 🆕 Automatic Updates

This plugin includes a GitHub update checker.  
When installed from the GitHub repository, you will receive update notifications right in your WordPress admin panel.

---

## ✅ Requirements

- WordPress **5.8+**
- PHP **7.4+**

---

## 👨‍💻 Author

[**Spark451**](https://www.spark451.com/) – Creative & Marketing Technology

## Changelog

### 1.0.4
* Add composer.json for Composer support

### 1.0.3
* Add fallback and conditional

### 1.0.2
* Update of description

### 1.0.1
* Update of readme
* Test auto update