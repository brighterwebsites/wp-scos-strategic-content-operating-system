/* Site Schema Module — admin JS
 * JSON-LD validation + "Generate from Business Info" button
 */
(function () {
    'use strict';

    // ── JSON validation ────────────────────────────────────────────────────
    function validate(textarea) {
        var id         = textarea.id;
        var validation = document.getElementById(id + '-validation');
        if (!validation) return;

        var value = textarea.value.trim();
        validation.className = 'scos-schema-validation';
        validation.textContent = '';
        validation.style.display = 'none';
        if (!value) return;

        var parsed = null;
        var usedPlaceholder = false;

        try {
            parsed = JSON.parse(value);
        } catch (e1) {
            var normalized = value.replace(/:\s*%%[^%]+%%/g, ': null');
            if (normalized !== value) {
                try {
                    parsed = JSON.parse(normalized);
                    usedPlaceholder = true;
                } catch (e2) { /* still invalid */ }
            }
        }

        if (parsed !== null) {
            validation.className = 'scos-schema-validation valid';
            if (Array.isArray(parsed)) {
                validation.textContent = '✓ Valid JSON – ' + parsed.length + ' block(s)' + (usedPlaceholder ? ' (%%…%% allowed)' : '');
            } else if (parsed && parsed['@type']) {
                validation.textContent = '✓ Valid JSON – @type: ' + parsed['@type'] + (usedPlaceholder ? ' (%%…%% allowed)' : '');
            } else {
                validation.textContent = '✓ Valid JSON' + (usedPlaceholder ? ' (%%…%% allowed)' : '');
            }
        } else {
            validation.className = 'scos-schema-validation invalid';
            var msg = '✗ Invalid JSON';
            try { JSON.parse(value); } catch (e) { msg = '✗ ' + e.message; }
            if (/\}\s*,?\s*\{/.test(value)) {
                msg += ' — Multiple blocks need [ { … }, { … } ]';
            }
            validation.textContent = msg;
        }
    }

    document.querySelectorAll('.scos-schema-json').forEach(function (ta) {
        var timer;
        ta.addEventListener('blur', function () { validate(ta); });
        ta.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { validate(ta); }, 400);
        });
        if (ta.value.trim()) validate(ta);
    });

    // ── Generate from Business Info button ────────────────────────────────
    var btn = document.getElementById('scos-generate-local-biz');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var d  = window.scosBizData  || {};
        var ss = window.scosSiteSchema || {};

        var orgType = d.organisation_type || 'LocalBusiness';
        // Normalise to schema.org @type
        if (orgType === 'Local Business') orgType = 'LocalBusiness';
        if (orgType === 'Organization')   orgType = 'Organization';
        if (orgType === 'Person')         orgType = 'Person';

        var schema = { '@context': 'https://schema.org', '@type': orgType };

        var homeUrl = ss.homeUrl || '';
        if (homeUrl) {
            schema['@id'] = homeUrl.replace(/\/$/, '') + '/#' + orgType.toLowerCase();
        }

        if (d.business_name)       schema['name']        = d.business_name;
        if (d.service_description) schema['description'] = d.service_description;
        if (homeUrl)               schema['url']         = homeUrl;
        if (d.phone_number)        schema['telephone']   = d.phone_number;
        if (d.email)               schema['email']       = d.email;
        if (d.founding_date)       schema['foundingDate']= d.founding_date;
        if (d.price_tier)          schema['priceRange']  = d.price_tier;

        // Address
        var hasAddress = d.address || d.city || d.state || d.postcode || d.country;
        if (hasAddress) {
            var addr = { '@type': 'PostalAddress' };
            if (d.address)  addr['streetAddress']   = d.address;
            if (d.city)     addr['addressLocality']  = d.city;
            if (d.state)    addr['addressRegion']    = d.state;
            if (d.postcode) addr['postalCode']       = d.postcode;
            if (d.country)  addr['addressCountry']   = d.country;
            schema['address'] = addr;
        }

        // Geo
        if (d.lat && d.long) {
            schema['geo'] = {
                '@type':     'GeoCoordinates',
                'latitude':  d.lat,
                'longitude': d.long
            };
        }

        // Images
        if (d.business_image) schema['image'] = d.business_image;
        if (d.business_logo)  schema['logo']  = d.business_logo;

        // sameAs
        var sameAs = [
            d.social_link_facebook,
            d.social_link_twitter,
            d.social_link_instagram,
            d.social_link_youtube,
            d.social_link_linkedin,
            d.social_link_pinterest,
            d.knowledge_panel_share
        ].filter(function (v) { return v && v.trim(); });
        if (sameAs.length) schema['sameAs'] = sameAs;

        var textarea = document.getElementById('scos_site_schema_local_business');
        if (textarea) {
            textarea.value = JSON.stringify(schema, null, 2);
            validate(textarea);
        }
    });

})();
