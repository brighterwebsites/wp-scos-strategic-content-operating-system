# Airtable bulk sync & record IDs (Step 2 – deferred)

## Planned work

1. **Bulk sync (per post type)**  
   Manual trigger to send all posts of a selected type to Airtable in one go, with rate limiting (e.g. X seconds between requests). For new setups or when the table is out of sync.

2. **Related fields → Airtable record IDs**  
   Send **record IDs** instead of display values for:
   - ALTC Category  
   - Topic  
   - Internal links (array)

   **ALTC Category** and **Topic** will live in **two new Airtable tables**. New settings fields needed for those table IDs.

3. **Internal links (self-reference)**  
   Content table links to itself. Currently we send post IDs to a text field; you copy into a “Internal Links to (related)” column. When the target post has no Airtable record yet, Airtable creates an empty row; when the post is synced later, a second row appears → duplicate IDs and broken links.

4. **Two-phase manual sync**  
   - **Phase 1:** Send all **static** content (no relationships). Rate-limited.  
   - **Phase 2:** Send **relationship** meta only (manual trigger after Phase 1).  
   All base records exist before we write links.

5. **Recommended run order (note next to “Run bulk sync”)**  
   1. ALTC Clusters  
   2. ALTC Topics  
   3. Content (basics)  
   4. Content stats  

6. **Make “Smart links”**  
   Make has a “use smart links” option to send primary key instead of looking up record ID. Check if Airtable API supports similar behaviour for link fields.
