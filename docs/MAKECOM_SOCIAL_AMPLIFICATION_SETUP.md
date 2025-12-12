# Make.com Social Amplification Setup Guide

**Version:** 1.0.0
**Last Updated:** December 12, 2024
**Purpose:** Complete setup guide for Make.com social media automation with YOURLS shortlinks

---

## Overview

This guide walks through setting up the complete Make.com automation workflow for social media post generation, including the YOURLS shortlink creation that was previously missing.

## What This Automation Does

1. **Triggers** when a WordPress post is published/updated
2. **Creates platform-specific shortlinks** via YOURLS (e.g., `bweb1.com.au/seo-signals-fb`)
3. **Generates AI prompts** with full content context
4. **Sends to ChatGPT** to create platform-optimized social posts
5. **Replaces [SHORTLINK] placeholder** with the actual shortlink
6. **Outputs to Google Sheets** for review before posting

---

## Prerequisites

### WordPress Setup ✅

1. **Brighter Core Plugin** installed and active
2. **Social Amplification Settings** configured:
   - Navigate to: `WordPress Admin > Support > Social Amplification`
   - Enable webhook checkbox
   - Webhook URL: `https://hook.us2.make.com/[your-webhook-id]`
   - YOURLS API URL: `http://bweb1.com.au/yourls-api.php`
   - YOURLS Signature Token: `1d8e2dfc33`
3. **API Token** for authentication:
   - Navigate to: `WordPress Admin > Support > API Settings`
   - Copy your API token (starts with `X-Brighter-Token`)

### YOURLS Setup ✅

Already configured at `http://bweb1.com.au`
- Signature token: `1d8e2dfc33`
- Username: `ainsssd8787p087`
- Password: `EdEudrgdDV723`

---

## Make.com Scenario Setup

### Step 1: Create New Scenario

1. Login to Make.com
2. Click "Create a new scenario"
3. Name it: "Social Media Amplification - WordPress"

### Step 2: Webhook Trigger

**Module:** Webhooks > Custom Webhook

1. Add webhook module
2. Click "Create a webhook"
3. Name: "WordPress Post Published"
4. Copy the webhook URL (e.g., `https://hook.us2.make.com/uokkbphdfvfdf7anyvmejmfi5nh47grq`)
5. Paste this URL into WordPress Social Amplification settings

**Expected Payload:**
```json
{
  "post_id": 123,
  "post_url": "https://example.com/blog/post-title/",
  "post_title": "Post Title",
  "post_type": "post",
  "post_excerpt": "Brief excerpt...",
  "post_date": "2025-12-02T10:30:00+00:00",
  "post_modified": "2025-12-02T11:00:00+00:00",
  "site_url": "https://example.com",
  "trigger_time": "2025-12-02 11:00:00"
}
```

### Step 3: Router (Split by Platform)

**Module:** Flow Control > Router

Create 5 routes (one per platform):
- Route 1: Facebook
- Route 2: LinkedIn
- Route 3: Twitter
- Route 4: Instagram
- Route 5: Google My Business

Each route will run in parallel, creating posts for all platforms simultaneously.

---

## Per-Platform Flow (Repeat for Each Route)

### Module A: HTTP Request - Create Shortlink

**Settings:**
- Method: `POST`
- URL: `{{site_url}}/wp-json/brighter-core/v1/social-amplification/create-shortlink`
- Headers:
  ```
  X-Brighter-Token: [your-api-token]
  Content-Type: application/json
  ```
- Body (JSON):
  ```json
  {
    "post_id": {{post_id}},
    "platform": "facebook",
    "format": "link"
  }
  ```

**Platform Values:**
- Facebook: `"platform": "facebook"`
- LinkedIn: `"platform": "linkedin"`
- Twitter: `"platform": "twitter"`
- Instagram: `"platform": "instagram"`
- GMB: `"platform": "gmb"`

**Response Example:**
```json
{
  "success": true,
  "shorturl": "http://bweb1.com.au/seo-signals-fb",
  "keyword": "seo-signals-fb",
  "destination_url": "https://example.com/blog/seo-signals/?utm_source=facebook&utm_medium=social&utm_content=blog_link",
  "meta": {
    "post_id": 123,
    "post_title": "How Google Receives SEO Signals",
    "breadcrumb": "seo-signals",
    "platform": "facebook",
    "content_type": "blog"
  }
}
```

**Save the response as:** `shortlink_response`

### Module B: HTTP Request - Generate AI Prompt

**Settings:**
- Method: `GET`
- URL: `{{site_url}}/wp-json/brighter-core/v1/social-amplification/generate-prompt`
- Headers:
  ```
  X-Brighter-Token: [your-api-token]
  ```
- Query Parameters:
  ```
  post_id: {{post_id}}
  platform: facebook
  talking_point_id: [select-from-wordpress]
  cta_focus: learn
  word_count: 90
  ```

**Parameter Options:**

**platform:**
- `facebook`
- `linkedin`
- `twitter`
- `instagram`
- `gmb`

**cta_focus:**
- `learn` - Educational, no ask ("Learn more", "Find out")
- `engage` - Soft ask ("Check it out", "Take the quiz")
- `act` - Clear CTA ("Get a quote", "Book now")

**talking_point_id:**
- Get available talking points from: `/wp-json/brighter-core/v1/social-amplification/talking-points`
- Create talking points in WordPress: `Admin > Talking Points > Add New`

**Response Example:**
```json
{
  "success": true,
  "prompt": "You are a social media content writer for Brighter Websites...",
  "meta": {
    "post_id": 123,
    "post_title": "How Google Receives SEO Signals",
    "platform": "facebook",
    "cta_focus": "learn"
  }
}
```

**Save the response as:** `prompt_response`

### Module C: OpenAI - Create Completion

**Settings:**
- Model: `gpt-4` or `gpt-4-turbo`
- Messages:
  ```json
  [
    {
      "role": "system",
      "content": "You are a social media content writer."
    },
    {
      "role": "user",
      "content": "{{prompt_response.prompt}}"
    }
  ]
  ```
- Temperature: `0.7`
- Max Tokens: `300`

**Output:** Social media post with `[SHORTLINK]` placeholder

**Save the response as:** `openai_response`

### Module D: Text Parser - Replace Shortlink

**Module:** Tools > Text Aggregator or Set Variable

**Operation:** Replace text
- Find: `[SHORTLINK]`
- Replace with: `{{shortlink_response.shorturl}}`
- Input text: `{{openai_response.choices[0].message.content}}`

**Output:** Final social media post with actual shortlink

**Save as:** `final_post`

### Module E: Google Sheets - Add Row

**Settings:**
- Spreadsheet: "Social Media Posts Queue"
- Sheet: "Posts"
- Values:
  ```
  Date: {{now}}
  Post Title: {{post_title}}
  Platform: Facebook
  Generated Post: {{final_post}}
  Shortlink: {{shortlink_response.shorturl}}
  Destination URL: {{shortlink_response.destination_url}}
  Status: Pending Review
  ```

**Alternative:** Send to Postly, Buffer, or directly to platform APIs

---

## Complete Flow Diagram

```
WordPress Post Published
    ↓
Make.com Webhook Receives
    ↓
Router (Split to 5 platforms)
    ↓
┌─────────────┬────────────┬────────────┬────────────┬────────────┐
│  Facebook   │  LinkedIn  │  Twitter   │ Instagram  │    GMB     │
└─────────────┴────────────┴────────────┴────────────┴────────────┘
    ↓             ↓            ↓            ↓            ↓
Create Shortlink (e.g., seo-signals-fb)
    ↓
Generate AI Prompt
    ↓
ChatGPT Creates Post
    ↓
Replace [SHORTLINK] Placeholder
    ↓
Output to Google Sheets
```

---

## Testing the Setup

### Test 1: Webhook Connection

1. Publish or update a WordPress post
2. Check Make.com webhook history
3. Verify post data is received correctly

**Expected Result:** Webhook shows the post data with post_id, post_url, post_title

### Test 2: Shortlink Creation

1. Manually trigger the shortlink creation module in Make.com
2. Use post_id from a real post
3. Check the response

**Expected Result:**
```json
{
  "success": true,
  "shorturl": "http://bweb1.com.au/[breadcrumb]-fb"
}
```

### Test 3: Visit Shortlink

1. Copy the shorturl from the response
2. Visit it in a browser
3. Verify it redirects to the correct post with UTM parameters

**Expected Result:**
- Redirects to: `https://yoursite.com/blog/post-slug/?utm_source=facebook&utm_medium=social&utm_content=blog_link`

### Test 4: Full Scenario

1. Publish a test post in WordPress
2. Wait for Make.com to process (usually < 30 seconds)
3. Check Google Sheets for 5 new rows (one per platform)

**Expected Result:** 5 social media posts with unique shortlinks per platform

---

## Troubleshooting

### Issue: Webhook Not Triggering

**Symptoms:** No data received in Make.com when publishing posts

**Solutions:**
1. Check WordPress webhook is enabled: `Admin > Social Amplification`
2. Verify webhook URL is correct (starts with `https://hook.us2.make.com/`)
3. Check WordPress error logs: `/wp-content/debug.log`
4. Test webhook manually using Postman or curl

### Issue: Shortlink Creation Fails

**Symptoms:** API returns error "YOURLS API URL is not configured"

**Solutions:**
1. Navigate to `Admin > Social Amplification`
2. Verify YOURLS settings are saved:
   - API URL: `http://bweb1.com.au/yourls-api.php`
   - Signature Token: `1d8e2dfc33`
3. Click "Save Settings" again
4. Test the connection by creating a post with a breadcrumb

### Issue: Authentication Error

**Symptoms:** API returns 401 Unauthorized

**Solutions:**
1. Check the API token is correct in Make.com headers
2. Token should start with characters like `brt_`
3. Get a fresh token from `Admin > Support > API Settings`
4. Ensure header is named exactly: `X-Brighter-Token`

### Issue: Breadcrumb Field Empty

**Symptoms:** Shortlink uses long slug instead of breadcrumb

**Solutions:**
1. Edit the post in WordPress
2. Find "Breadcrumb (Short Title)" field in the sidebar
3. Enter a short version (e.g., "SEO Signals")
4. Save the post
5. Breadcrumb auto-converts to lowercase: `seo-signals`

### Issue: YOURLS Returns "Keyword Already Exists"

**Symptoms:** Shortlink creation returns error about duplicate keyword

**Solutions:**
This is actually OK! The system will return the existing shortlink.
- If you need a new shortlink, change the breadcrumb field in WordPress
- Or manually delete the old shortlink in YOURLS admin
- Platform suffix prevents most duplicates (seo-signals-fb vs seo-signals-li)

### Issue: UTM Parameters Not Working

**Symptoms:** Analytics not tracking social traffic correctly

**Solutions:**
1. Check the `destination_url` in the shortlink response includes UTM parameters
2. Format should be: `?utm_source=facebook&utm_medium=social&utm_content=blog_link`
3. If missing, check WordPress site uses correct permalink structure (not plain)
4. Verify Google Analytics is configured to track UTM parameters

---

## API Endpoints Reference

### Create Shortlink

**Endpoint:** `POST /wp-json/brighter-core/v1/social-amplification/create-shortlink`

**Headers:**
```
X-Brighter-Token: your-token-here
Content-Type: application/json
```

**Body:**
```json
{
  "post_id": 123,
  "platform": "facebook",
  "format": "link"
}
```

**Response:**
```json
{
  "success": true,
  "shorturl": "http://bweb1.com.au/seo-signals-fb",
  "keyword": "seo-signals-fb",
  "destination_url": "https://example.com/blog/post/?utm_source=facebook&utm_medium=social&utm_content=blog_link",
  "meta": {
    "post_id": 123,
    "post_title": "How Google Receives SEO Signals",
    "post_url": "https://example.com/blog/post/",
    "breadcrumb": "seo-signals",
    "platform": "facebook",
    "content_type": "blog",
    "format": "link"
  }
}
```

### Generate AI Prompt

**Endpoint:** `GET /wp-json/brighter-core/v1/social-amplification/generate-prompt`

**Headers:**
```
X-Brighter-Token: your-token-here
```

**Query Parameters:**
```
post_id: 123
platform: facebook
talking_point_id: 45
cta_focus: learn
word_count: 90
```

**Response:**
```json
{
  "success": true,
  "prompt": "Full AI prompt with instructions...",
  "meta": {
    "post_id": 123,
    "post_title": "How Google Receives SEO Signals",
    "post_url": "https://example.com/blog/post/",
    "talking_point": "SEO Fundamentals",
    "cta_focus": "learn",
    "platform": "facebook"
  }
}
```

### Get Talking Points

**Endpoint:** `GET /wp-json/brighter-core/v1/social-amplification/talking-points`

**Headers:**
```
X-Brighter-Token: your-token-here
```

**Optional Query Parameters:**
```
content_type: blog
```

**Response:**
```json
{
  "success": true,
  "count": 5,
  "items": [
    {
      "id": 45,
      "title": "SEO Fundamentals",
      "content_type": "blog",
      "context": "Explain the basics...",
      "example": "Example angles..."
    }
  ]
}
```

---

## UTM Parameter Format

All shortlinks automatically include UTM parameters:

**Format:**
```
utm_source={platform}
utm_medium=social
utm_content={content_type}_link
```

**Examples:**

**Facebook Blog Post:**
```
utm_source=facebook&utm_medium=social&utm_content=blog_link
```

**LinkedIn Project:**
```
utm_source=linkedin&utm_medium=social&utm_content=project_link
```

**Twitter Service Page:**
```
utm_source=twitter&utm_medium=social&utm_content=service_link
```

**Content Types:**
- `blog` - Blog posts
- `project` - Portfolio/projects
- `service` - Service pages
- `pillar` - Pillar content
- `page` - General pages
- `faq` - FAQ pages

**Formats** (for future use):
- `link` - Standard text link (default)
- `img` - Image post
- `reel` - Video reel
- `video` - Video post

---

## Platform-Specific Notes

### Facebook
- Keyword suffix: `-fb`
- Example: `seo-signals-fb`
- Ideal word count: 90-120 words
- Emojis: Sparingly (mainly for CTA)
- Hashtags: 3-4 at end

### LinkedIn
- Keyword suffix: `-li`
- Example: `seo-signals-li`
- Ideal word count: 120-150 words
- Professional tone
- Hashtags: 2-3 relevant

### Twitter
- Keyword suffix: `-tw`
- Example: `seo-signals-tw`
- Max: 280 characters
- Concise and punchy
- Hashtags: 1-2

### Instagram
- Keyword suffix: `-ig`
- Example: `seo-signals-ig`
- Visual-first mindset
- First line is hook
- Hashtags: 5-8 acceptable

### Google My Business
- Keyword suffix: `-gmb`
- Example: `seo-signals-gmb`
- Short and local-focused
- **Important:** NO URLs in GMB posts (platform restriction)
- Focus on value and location relevance

---

## Next Steps

1. ✅ Configure YOURLS settings in WordPress
2. ✅ Set up Make.com webhook
3. ✅ Create Make.com scenario with router
4. ✅ Add shortlink creation module per platform
5. ✅ Add AI prompt generation module
6. ✅ Add ChatGPT module
7. ✅ Add text replacement module
8. ✅ Add Google Sheets output
9. ✅ Test with a sample post
10. ✅ Monitor and adjust

---

## Maintenance

### Weekly
- Review Google Sheets for pending posts
- Check for failed scenarios in Make.com
- Verify shortlinks are working

### Monthly
- Audit UTM tracking in Google Analytics
- Review talking points effectiveness
- Update platform-specific rules if needed

### Quarterly
- Check YOURLS storage limits
- Review API token security
- Update documentation with learnings

---

## Support

**WordPress Issues:**
- Check: `/wp-content/debug.log`
- Settings: `Admin > Social Amplification`
- API Docs: `Admin > Support > API Settings`

**YOURLS Issues:**
- Admin: `http://bweb1.com.au/admin/`
- API Docs: Official YOURLS documentation

**Make.com Issues:**
- Scenario history: View execution logs
- Webhook testing: Use "Run once" feature
- Support: Make.com help center

---

**Document Version:** 1.0.0
**Last Updated:** December 12, 2024
**Next Review:** March 2025
