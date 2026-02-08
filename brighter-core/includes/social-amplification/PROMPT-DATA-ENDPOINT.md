# Prompt-Data Endpoint — Scenario & Strategy

## Scenario (Make.com flow)

1. **Trigger:** Webhook fires on "just published" (or manual). Payload: `post_id`, `url`, etc.  
   - Webhook URL comes from `bw_social_webhook_trigger` (Brighter API).

2. **HTTP request:** Make calls  
   `GET /wp-json/brighter-core/v1/social-amplification/generate-prompt?post_id={{post_id}}`  
   (or `url={{url}}` if no `post_id`).  
   - Same route as before; **response format only** changes.

3. **WP response:** JSON with `post_id`, `source_url`, `context`, `framing_options`, `source_material`, `source_tldr`, `count_h2`.  
   - **No prompt is built in WordPress.** Make builds the prompt from this data.

4. **Make Step 1 — Gemini:**  
   - **Input:** `framing_options`, `source_material`, `context`, `count_h2` (as X).  
   - **Task:** Identify **X unique points** from the source material (X = H2 count).  
   - **Output:** JSON (used in step 2).

5. **Make Step 2 — GPT:**  
   - **Input:** Gemini JSON + selected fields from the HTTP response.  
   - **Task:** Construct social prompt; GPT returns final post (JSON in / JSON out).

6. **Existing Make steps:** YOURLS shortlink creation, etc. continue to run as today.

---

## Strategy

- **Update** the existing `generate-prompt` endpoint (same URL).  
- **Request:** `GET` with `post_id` **or** `url` only.  
  - Remove: `talking_point_id`, `cta_focus`, `platform`, `word_count`.
- **Response:** Structured JSON (see below). No `prompt` key.
- **Content type:** Use `BW_Content_Type_Helper::get_content_type( $post_id, $post_type )`.
- **Framing options:**  
  - Query `bw_talking_point` posts where taxonomy `bw_content_type` matches the post’s **content type**.  
  - Return array of `{ label, type, context, hook_examples, cta_examples, target_length }` per talking point.
- **Source TLDR:** `bw_tldr`; fallback to `get_the_excerpt()` if empty.
- **Count H2:** `bw_h2_count`; exposed as `count_h2`.

---

## Request

```
GET /wp-json/brighter-core/v1/social-amplification/generate-prompt
Query params:
  - post_id (int, optional if url provided)
  - url (string, optional if post_id provided)
Headers:
  - X-Brighter-Token: <token>
```

---

## Response (JSON)

```json
{
  "post_id": 123,
  "source_url": "https://example.com/post-slug/",
  "context": {
    "title": "Post Title",
    "type": "service",
    "purpose": "service-page",
    "intent": "informational"
  },
  "framing_options": [
    {
      "label": "Feature",
      "type": "service",
      "context": "Translate each feature into a tangible business result…",
      "hook_examples": ["Faster load speeds…", "Real data dashboards…"],
      "cta_examples": ["See how it drives real results.", "Find out what this means…"],
      "target_length": "50-130"
    },
    {
      "label": "Benefit",
      "type": "service",
      "context": "…",
      "hook_examples": ["…"],
      "cta_examples": ["…"],
      "target_length": "80-120"
    }
  ],
  "source_material": "Full post content, strip tags…",
  "source_tldr": "bw_tldr or excerpt",
  "count_h2": 5
}
```

- `context.type`: from `BW_Content_Type_Helper::get_content_type`.
- `context.purpose`: `bw_purpose` meta.
- `context.intent`: `bw_intent` meta.
- `framing_options[].label`: talking point title.
- `framing_options[].type`: content-type slug used for the query (same for all when filtered by one type).
- `framing_options[].context`: `_bw_tp_context`.
- `framing_options[].hook_examples`: `_bw_tp_example` → split into array (newlines or commas).
- `framing_options[].cta_examples`: `_bw_tp_cta_example` → split into array.
- `framing_options[].target_length`: `"min-max"` from `_bw_tp_word_count_min` / `_bw_tp_word_count_max`.

---

## Implementation notes

- **Prompt construction:** Done in Make (Gemini → GPT). This endpoint only supplies data.
- **Framing:** One `framing_options` set per request, based on the post’s content type.
- **H2 count:** Drives “X unique points” in Gemini (X = `count_h2`).
