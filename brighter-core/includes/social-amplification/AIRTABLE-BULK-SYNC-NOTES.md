# Airtable bulk sync & record IDs

## Implemented

1. **Settings (bw-social-amplification)**
   - Airtable Content Table ID (renamed from Table ID)
   - Airtable ALTC Table ID
   - Airtable Topics Table ID

2. **Term sync on save**
   - ALTC Strategic Lens terms → ALTC table on created/edited
   - ALTC Topic terms → Topics table on created/edited
   - Stores `_airtable_record_id` in term meta

3. **Content sync with Linked Records**
   - ALTC Cluster: sends `['recXXX']` when term has Airtable record ID
   - Topics: sends `['recXXX']` when term has Airtable record ID
   - Internal Links: sends `['recXXX','recYYY']` (Linked Record to same Content table)

4. **Bulk sync ("Seed Airtable — Sync All" button)**
   - Order: 1) ALTC Clusters, 2) Topics, 3) Content phase 1 (static), 4) Content phase 2 (Internal Links)
   - Two-phase content: Phase 1 creates/updates records without Internal Links; Phase 2 patches Internal Links only (requires Phase 1 first)

5. **Rate limiting**
   - ~250–300ms between requests to avoid Airtable rate limits

## Airtable table structure

- **ALTC / Topics tables**: Primary field "Name" (term name)
- **Content table**: ALTC Cluster and Topics must be Linked Record type pointing to respective tables
