/**
 * SCOS — native WebMCP tool registration
 *
 * v1.0 | 2026-10-21
 *
 * Registers the SCOS tools with the browser's own agent API
 * (document.modelContext, WebML CG draft). Older Chrome builds expose it as
 * navigator.modelContext. With neither present this file does nothing.
 *
 * Tool definitions come from WebMCP_Tools.php via scosWebMCPNative.tools.
 */
(function () {
    'use strict';

    var mc = (document.modelContext || navigator.modelContext);
    var cfg = window.scosWebMCPNative;

    if (!mc || typeof mc.registerTool !== 'function' || !cfg || !cfg.tools) {
        return;
    }

    function buildUrl(def, input) {
        var path = def.path.replace(/\{(\w+)\}/g, function (m, key) {
            return encodeURIComponent(input[key] == null ? '' : input[key]);
        });
        var qs = (def.query || []).filter(function (key) {
            return input[key] != null && input[key] !== '';
        }).map(function (key) {
            return key + '=' + encodeURIComponent(input[key]);
        }).join('&');

        return def.base.replace(/\/$/, '') + path + (qs ? '?' + qs : '');
    }

    cfg.tools.forEach(function (def) {
        var tool = {
            name: def.name,
            description: def.description,
            inputSchema: def.inputSchema,
            annotations: def.annotations || {},
            execute: function (input, options) {
                return fetch(buildUrl(def, input || {}), {
                    credentials: 'same-origin',
                    signal: options && options.signal
                }).then(function (r) { return r.json(); });
            }
        };

        try {
            Promise.resolve(mc.registerTool(tool)).catch(function (err) {
                console.warn('[SCOS WebMCP] ' + def.name + ' not registered:', err);
            });
        } catch (err) {
            console.warn('[SCOS WebMCP] ' + def.name + ' not registered:', err);
        }
    });
}());
