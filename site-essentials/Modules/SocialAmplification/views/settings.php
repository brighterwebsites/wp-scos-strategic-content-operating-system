<?php
/**
 * Social Amplification — Settings page view
 *
 * Rendered by SocialAmplification_Module::render_settings() via Admin_UI.
 * SCOS-SA-PASS1 — full UI rebuild to SCOS design system (Template A: tabbed settings).
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Modules\SocialAmplification\Post_Framing;
use SiteEssentials\Modules\SocialAmplification\SocialAmplification_Module as SMA;

// SCOS-SA-PASS1 — option reader: existing keys preserved; scos_sa_ prefix is target migration state.
// TODO: migrate key to scos_sa_ prefix — SCOS-SA-PASS1
$yourls_url  = SMA::get_option( 'scos_sma_yourls_url',       'bw_yourls_api_url' );
$yourls_sig  = SMA::get_option( 'scos_sma_yourls_signature', 'bw_yourls_signature' );
$yourls_user = SMA::get_option( 'scos_sma_yourls_username',  'bw_yourls_username' );
$yourls_pass = SMA::get_option( 'scos_sma_yourls_password',  'bw_yourls_password' );

// TODO: migrate key to scos_sa_ prefix — SCOS-SA-PASS1
$postly_api_key        = get_option( 'bw_postly_api_key', '' );
$postly_workspace_id   = get_option( 'bw_postly_workspace_id', '' );
$postly_channel_ids    = get_option( 'bw_postly_channel_ids', '' );
$postly_gmb_channel_id = get_option( 'se_postly_gmb_channel_id', '' );
$acf_gallery_keys      = get_option( 'bw_social_acf_gallery_keys', '' );
$acf_featured_key      = get_option( 'bw_social_acf_featured_key', '' );
$webhook_secret        = get_option( 'bw_social_webhook_secret', '' );
$social_enabled        = get_option( 'bw_social_enabled', '' );
$publish_time_min      = get_option( 'bw_social_publish_time_min', '09:00' );
$publish_time_max      = get_option( 'bw_social_publish_time_max', '17:00' );

// New scos_sa_* scheduling fields
$postly_post_count    = get_option( 'scos_sa_postly_post_count', 3 );
$backfill_date_from   = get_option( 'scos_sa_backfill_date_from', '' );
$backfill_date_to     = get_option( 'scos_sa_backfill_date_to', '' );
$backfill_limit       = get_option( 'scos_sa_backfill_limit', 5 );

// TODO: migrate key to scos_sa_ prefix — SCOS-SA-PASS1
$make_webhook_url    = SMA::get_option( 'scos_sma_webhook_url',     'bw_social_webhook_url' );
$make_auto_trigger   = SMA::get_option( 'scos_sma_webhook_enabled', 'bw_social_webhook_enabled', 0 );

$page_url = admin_url( 'admin.php?page=site-essentials-social-amplification' );
?>

<?php // SCOS-SA-PASS1 — page chrome: canonical header + scos__tabs nav ?>

<?php if ( isset( $_GET['scos_sma_saved'] ) ) : ?>
  <div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-5)">
    <?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?>
  </div>
<?php endif; ?>

<header class="scos__header">
  <div>
    <h1 class="scos__title"><?php esc_html_e( 'Social Amplification', 'site-essentials' ); ?></h1>
    <p class="scos__subtitle">Site Essentials &rsaquo; Social Amplification</p>
  </div>
  <div class="scos__header-actions">
    <button type="submit" id="scos-sa-header-save" form="scos-sa-form-yourls" class="scos-btn scos-btn--primary">
      <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
    </button>
  </div>
</header>

<nav class="scos__tabs" id="scos-sa-tabs">
  <a href="#settings" class="scos__tab" data-tab="settings"><?php esc_html_e( 'Settings', 'site-essentials' ); ?></a>
  <a href="#yourls"   class="scos__tab" data-tab="yourls"><?php esc_html_e( 'Short link – YOURLS', 'site-essentials' ); ?></a>
  <a href="#postly"   class="scos__tab" data-tab="postly"><?php esc_html_e( 'Postly.ai API settings', 'site-essentials' ); ?></a>
  <a href="#make"     class="scos__tab" data-tab="make"><?php esc_html_e( 'Make.com settings', 'site-essentials' ); ?></a>
</nav>

<?php // SCOS-SA-PASS1 — minimal hash-based tab switcher ?>
<script>
(function() {
  var TABS     = ['settings','yourls','postly','make'];
  var FORMS    = { yourls: 'scos-sa-form-yourls', postly: 'scos-sa-form-postly', make: 'scos-sa-form-make' };
  var panels   = {};
  var links    = {};
  var saveBtn;

  function activate(tab) {
    if ( TABS.indexOf(tab) === -1 ) tab = TABS[0];
    TABS.forEach(function(t) {
      if (panels[t]) panels[t].style.display = (t === tab) ? '' : 'none';
      if (links[t])  links[t].classList.toggle('scos__tab--active', t === tab);
    });
    // Update header Save button: show only when the active tab has a form
    if (saveBtn) {
      var formId = FORMS[tab];
      if (formId) {
        saveBtn.setAttribute('form', formId);
        saveBtn.style.display = '';
      } else {
        saveBtn.style.display = 'none';
      }
    }
    if (history.replaceState) history.replaceState(null, '', '#' + tab);
  }

  document.addEventListener('DOMContentLoaded', function() {
    saveBtn = document.getElementById('scos-sa-header-save');
    TABS.forEach(function(t) {
      panels[t] = document.getElementById('scos-sa-panel-' + t);
      links[t]  = document.querySelector('[data-tab="' + t + '"]');
      if (links[t]) {
        links[t].addEventListener('click', function(e) {
          e.preventDefault();
          activate(t);
        });
      }
    });

    // On load: check hash first, then ?scos_sma_tab= query param
    var hash    = window.location.hash.replace('#','');
    var params  = new URLSearchParams(window.location.search);
    var initial = TABS.indexOf(hash) !== -1 ? hash : ( params.get('scos_sma_tab') || TABS[0] );
    activate(initial);
  });
})();
</script>

<?php // ──────────────────────────────────────────────────────────────────────────── ?>
<?php // Tab 1 — Settings                                                            ?>
<?php // SCOS-SA-PASS1 — new tab; CPT buttons + guide tiles                         ?>
<?php // ──────────────────────────────────────────────────────────────────────────── ?>

<div id="scos-sa-panel-settings">

  <div class="scos-card">
    <div class="scos-card__header">
      <h3 class="scos-card__title"><?php esc_html_e( 'Post framing & social post types', 'site-essentials' ); ?></h3>
      <p class="scos-card__desc"><?php esc_html_e( 'Manage the framing types and post type templates used during social content generation.', 'site-essentials' ); ?></p>
    </div>
    <div class="scos-card__body">
      <div style="display:flex;gap:var(--scos-s-2)">
        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Framing::POST_TYPE ) ); ?>"
           class="scos-btn">
          <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Post framing', 'site-essentials' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Framing::POST_TYPE ) ); ?>"
           class="scos-btn">
          <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add post frame', 'site-essentials' ); ?>
        </a>
      </div>
    </div>
  </div>

  <div class="scos-card">
    <div class="scos-card__header">
      <h3 class="scos-card__title"><?php esc_html_e( 'Social Amplification setup guide', 'site-essentials' ); ?></h3>
      <p class="scos-card__desc">
        <?php esc_html_e( 'Create, schedule, and automate social content using your website\'s existing content as inspiration and context. Uses on-site text and images for automated post creation — publish in one click direct from your website, or set up a review gate before anything goes live.', 'site-essentials' ); ?>
      </p>
    </div>
    <div class="scos-card__body">
      <div class="scos-support__grid">

        <a href="https://brighterwebsites.com.au/software/social-amplification/make-com-integration/"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong><?php esc_html_e( 'Social automation via Make.com', 'site-essentials' ); ?></strong>
          <span><?php esc_html_e( 'Set up website-to-social automation using Make.com scenarios.', 'site-essentials' ); ?></span>
        </a>

        <a href="https://brighterwebsites.com.au/software/social-amplification/postly-ai-integration/"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong><?php esc_html_e( 'Social automation via Postly.ai', 'site-essentials' ); ?></strong>
          <span><?php esc_html_e( 'Schedule and automate posts using the Postly.ai API.', 'site-essentials' ); ?></span>
        </a>

        <a href="https://brighterwebsites.com.au/software/social-amplification/yourls-shortlink-integration/"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong><?php esc_html_e( 'Short links & UTM tracking via YOURLS', 'site-essentials' ); ?></strong>
          <span><?php esc_html_e( 'Auto-generate branded short links with UTM parameters on post creation.', 'site-essentials' ); ?></span>
        </a>

        <a href="https://brighterwebsites.com.au/software/social-amplification/postly-ai-integration/#ai-knowledge"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong><?php esc_html_e( 'AI content generation setup', 'site-essentials' ); ?></strong>
          <span><?php esc_html_e( 'Configure Claude API and AI knowledge documents for brand-voice content.', 'site-essentials' ); ?></span>
        </a>

      </div>
    </div>
  </div>

</div><!-- /#scos-sa-panel-settings -->

<?php // ──────────────────────────────────────────────────────────────────────────── ?>
<?php // Tab 2 — YOURLS                                                              ?>
<?php // SCOS-SA-PASS1 — scos-card, scos-form; existing keys preserved              ?>
<?php // ──────────────────────────────────────────────────────────────────────────── ?>

<div id="scos-sa-panel-yourls">

  <form id="scos-sa-form-yourls" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
    <input type="hidden" name="action" value="site_essentials_save_sma">
    <input type="hidden" name="_scos_sma_tab" value="yourls">

    <div class="scos-card">
      <div class="scos-card__header">
        <h3 class="scos-card__title"><?php esc_html_e( 'Shortlink integration – YOURLS', 'site-essentials' ); ?></h3>
        <a href="https://brighterwebsites.com.au/software/social-amplification/yourls-shortlink-integration/"
           target="_blank" rel="noopener" class="scos-badge scos-badge--soft"><?php esc_html_e( 'Guide', 'site-essentials' ); ?></a>
      </div>
      <div class="scos-card__body">

        <p class="description" style="margin-bottom:var(--scos-s-4)">
          <?php esc_html_e( 'Configure your self-hosted YOURLS installation. The shortlink slug entered on each post page is used as the YOURLS keyword to create your shortlink. UTM parameters are automatically attached to the redirected URL.', 'site-essentials' ); ?>
        </p>

        <div class="scos-notice scos-notice--info" style="margin-bottom:var(--scos-s-5)">
          <strong class="scos-notice__title"><?php esc_html_e( 'Example redirection', 'site-essentials' ); ?></strong>
          <code>shrtlnk.com/mypage</code> &rarr;
          <code>mydomainname.com.au/my-long-page-title?utm_source=social_media&amp;utm_medium=social&amp;utm_content=[page-type]&amp;utm_campaign=none</code>
        </div>

        <?php // SCOS-SA-PASS1 — input name uses scos_sma_yourls_url (existing key); slug label shows target scos_sa_ key ?>
        <?php // TODO: migrate key scos_sma_yourls_url → scos_sa_yourls_api_url — SCOS-SA-PASS1 ?>
        <table class="scos-form">
          <tbody>
            <tr>
              <th>
                <label for="scos_sma_yourls_url"><?php esc_html_e( 'YOURLS API URL', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_yourls_api_url</div>
              </th>
              <td>
                <input id="scos_sma_yourls_url" name="scos_sma_yourls_url" type="url"
                       class="scos-input" placeholder="https://yourdomain.com/yourls-api.php"
                       value="<?php echo esc_attr( $yourls_url ); ?>">
                <p class="description"><?php esc_html_e( 'Full path to yourls-api.php on your YOURLS installation.', 'site-essentials' ); ?></p>
              </td>
            </tr>
            <?php // TODO: migrate key scos_sma_yourls_signature → scos_sa_yourls_token — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="scos_sma_yourls_signature"><?php esc_html_e( 'Signature token', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_yourls_token</div>
              </th>
              <td>
                <input id="scos_sma_yourls_signature" name="scos_sma_yourls_signature" type="text"
                       class="scos-input scos-input--mono"
                       value="<?php echo esc_attr( $yourls_sig ); ?>">
                <p class="description">
                  <strong><?php esc_html_e( 'Recommended.', 'site-essentials' ); ?></strong>
                  <?php esc_html_e( 'Found in YOURLS Admin &rarr; Tools &rarr; Signature Token.', 'site-essentials' ); ?>
                </p>
              </td>
            </tr>
          </tbody>
        </table>

        <hr style="border:none;border-top:1px solid var(--scos-border);margin:var(--scos-s-5) 0">

        <table class="scos-form">
          <tbody>
            <tr>
              <th>
                <label for="scos_sma_yourls_username"><?php esc_html_e( 'Username', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_yourls_username</div>
              </th>
              <td>
                <input id="scos_sma_yourls_username" name="scos_sma_yourls_username" type="text"
                       class="scos-input"
                       value="<?php echo esc_attr( $yourls_user ); ?>">
                <p class="description"><?php esc_html_e( 'Only needed if not using a signature token.', 'site-essentials' ); ?></p>
              </td>
            </tr>
            <tr>
              <th>
                <label for="scos_sma_yourls_password"><?php esc_html_e( 'Password', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_yourls_password</div>
              </th>
              <td>
                <input id="scos_sma_yourls_password" name="scos_sma_yourls_password" type="password"
                       class="scos-input"
                       value="<?php echo esc_attr( $yourls_pass ); ?>">
                <p class="description"><?php esc_html_e( 'Only needed if not using a signature token.', 'site-essentials' ); ?></p>
              </td>
            </tr>
          </tbody>
        </table>

      </div>
      <div class="scos-card__footer">
        <button type="submit" class="scos-btn scos-btn--primary">
          <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
        </button>
      </div>
    </div><!-- /.scos-card -->

  </form>

</div><!-- /#scos-sa-panel-yourls -->

<?php // ──────────────────────────────────────────────────────────────────────────── ?>
<?php // Tab 3 — Postly.ai API settings                                              ?>
<?php // SCOS-SA-PASS1 — 3 cards: Scheduling, Postly API, AI knowledge docs         ?>
<?php // ──────────────────────────────────────────────────────────────────────────── ?>

<div id="scos-sa-panel-postly">

  <form id="scos-sa-form-postly" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
    <input type="hidden" name="action" value="site_essentials_save_sma">
    <input type="hidden" name="_scos_sma_tab" value="postly">

    <?php // ── Card 1: Scheduling ──────────────────────────────────────────────── ?>

    <div class="scos-card">
      <div class="scos-card__header">
        <h3 class="scos-card__title"><?php esc_html_e( 'Scheduling', 'site-essentials' ); ?></h3>
        <a href="https://brighterwebsites.com.au/software/social-amplification/postly-ai-integration/"
           target="_blank" rel="noopener" class="scos-badge scos-badge--soft"><?php esc_html_e( 'Guide', 'site-essentials' ); ?></a>
      </div>
      <div class="scos-card__body">

        <p class="scos__section-label"><?php esc_html_e( 'New content', 'site-essentials' ); ?></p>

        <table class="scos-form">
          <tbody>

            <?php // TODO: migrate key bw_social_enabled → scos_sa_postly_enabled — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_social_enabled"><?php esc_html_e( 'Enable social amplification', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_enabled</div>
              </th>
              <td>
                <label class="scos-checkbox-row">
                  <input id="bw_social_enabled" name="bw_social_enabled" type="checkbox"
                         value="1" <?php checked( $social_enabled, '1' ); ?>>
                  <?php esc_html_e( 'Automatically amplify when a Projects post is published', 'site-essentials' ); ?>
                </label>
                <p class="description">
                  <?php
                  printf(
                    /* translators: %s: link to AI API Keys settings page */
                    esc_html__( 'Requires Postly API key, Workspace ID, and Anthropic API key (in %s) to be configured.', 'site-essentials' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=site-essentials-settings&tab=ai-keys' ) ) . '">' . esc_html__( 'Settings &rarr; AI API Keys', 'site-essentials' ) . '</a>'
                  );
                  ?>
                </p>
              </td>
            </tr>

            <?php // TODO: migrate keys bw_social_publish_time_min/max → scos_sa_postly_window_from/to — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label><?php esc_html_e( 'Publish time window', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_window_from / _to</div>
              </th>
              <td>
                <div style="display:flex;align-items:center;gap:var(--scos-s-2)">
                  <span><?php esc_html_e( 'From', 'site-essentials' ); ?></span>
                  <input id="bw_social_publish_time_min" name="bw_social_publish_time_min"
                         type="time" class="scos-input" style="width:130px"
                         value="<?php echo esc_attr( $publish_time_min ); ?>">
                  <span><?php esc_html_e( 'To', 'site-essentials' ); ?></span>
                  <input id="bw_social_publish_time_max" name="bw_social_publish_time_max"
                         type="time" class="scos-input" style="width:130px"
                         value="<?php echo esc_attr( $publish_time_max ); ?>">
                </div>
                <p class="description">
                  <?php esc_html_e( 'Posts are scheduled at a random time within this window (site timezone). Slot 1 is always pushed at least 60 minutes into the future to allow for Postly processing and approval.', 'site-essentials' ); ?>
                </p>
              </td>
            </tr>

            <tr>
              <th>
                <label for="scos_sa_postly_post_count"><?php esc_html_e( 'Posts to create', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_post_count</div>
              </th>
              <td>
                <input id="scos_sa_postly_post_count" name="scos_sa_postly_post_count"
                       type="number" class="scos-input" style="width:80px" min="1" max="10"
                       value="<?php echo esc_attr( $postly_post_count ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Number of social posts generated per project publish. GMB always creates 1 post regardless of this setting.', 'site-essentials' ); ?>
                </p>
              </td>
            </tr>

          </tbody>
        </table>

        <hr style="border:none;border-top:1px solid var(--scos-border);margin:var(--scos-s-5) 0">

        <p class="scos__section-label"><?php esc_html_e( 'Existing content backfill', 'site-essentials' ); ?></p>

        <?php
        // SCOS-SA-PASS1 — backfill data attributes preserved so backfill.js continues to work.
        $bw_secret = get_option( 'bw_social_webhook_secret', '' );
        ?>
        <div id="scos-sa-backfill-wrap"
             data-secret="<?php echo esc_attr( $bw_secret ); ?>"
             data-rest="<?php echo esc_attr( rest_url( 'bw-social/v1/backfill' ) ); ?>">

          <table class="scos-form">
            <tbody>
              <tr>
                <th>
                  <label><?php esc_html_e( 'Date range', 'site-essentials' ); ?></label>
                  <div class="scos-form__slug">scos_sa_backfill_date_from / _to</div>
                </th>
                <td>
                  <div style="display:flex;align-items:center;gap:var(--scos-s-2)">
                    <span><?php esc_html_e( 'From', 'site-essentials' ); ?></span>
                    <input name="scos_sa_backfill_date_from" id="scos_sa_backfill_from"
                           type="date" class="scos-input" style="width:160px"
                           value="<?php echo esc_attr( $backfill_date_from ?: gmdate( 'Y-m-01' ) ); ?>">
                    <span><?php esc_html_e( 'To', 'site-essentials' ); ?></span>
                    <input name="scos_sa_backfill_date_to" id="scos_sa_backfill_to"
                           type="date" class="scos-input" style="width:160px"
                           value="<?php echo esc_attr( $backfill_date_to ?: gmdate( 'Y-m-d' ) ); ?>">
                  </div>
                  <p class="description"><?php esc_html_e( 'Run social amplification for existing project posts published within this date range.', 'site-essentials' ); ?></p>
                </td>
              </tr>
              <tr>
                <th>
                  <label for="scos_sa_backfill_limit"><?php esc_html_e( 'Limit', 'site-essentials' ); ?></label>
                  <div class="scos-form__slug">scos_sa_backfill_limit</div>
                </th>
                <td>
                  <input id="scos_sa_backfill_limit" name="scos_sa_backfill_limit"
                         type="number" class="scos-input" style="width:80px" min="1"
                         value="<?php echo esc_attr( $backfill_limit ); ?>">
                  <p class="description"><?php esc_html_e( 'Maximum number of posts to process in this backfill run.', 'site-essentials' ); ?></p>
                  <div class="scos-notice scos-notice--info" style="margin-top:var(--scos-s-3)">
                    <?php esc_html_e( 'Additional scheduling options (days between posts, start date offset, per-post count) are available via CLI.', 'site-essentials' ); ?>
                    <a href="https://brighterwebsites.com.au/software/social-amplification/social-amplification-technical-documentation/"
                       target="_blank" rel="noopener"><?php esc_html_e( 'View CLI reference &rarr;', 'site-essentials' ); ?></a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div id="scos-sa-backfill-status" class="scos-sa-result" hidden></div>
          <div id="scos-sa-backfill-results"></div>

        </div><!-- /#scos-sa-backfill-wrap -->

      </div>
      <div class="scos-card__footer" style="display:flex;justify-content:space-between;align-items:center">
        <button type="button" id="scos-sa-run-backfill" class="scos-btn">
          <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run backfill', 'site-essentials' ); ?>
        </button>
        <button type="submit" class="scos-btn scos-btn--primary">
          <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
        </button>
      </div>
    </div><!-- /.scos-card Scheduling -->

    <?php // ── Card 2: Postly API – integration ──────────────────────────────── ?>

    <div class="scos-card">
      <div class="scos-card__header">
        <h3 class="scos-card__title"><?php esc_html_e( 'Postly API – integration', 'site-essentials' ); ?></h3>
      </div>
      <div class="scos-card__body">

        <p class="scos__section-label"><?php esc_html_e( 'Workspace settings', 'site-essentials' ); ?></p>

        <table class="scos-form">
          <tbody>
            <?php // TODO: migrate key bw_postly_api_key → scos_sa_postly_api_key — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_postly_api_key"><?php esc_html_e( 'Postly API key', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_api_key</div>
              </th>
              <td>
                <input id="bw_postly_api_key" name="bw_postly_api_key" type="text"
                       class="scos-input scos-input--mono"
                       value="<?php echo esc_attr( $postly_api_key ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Your Postly.ai API key. Get it from', 'site-essentials' ); ?>
                  <a href="https://app.postly.ai" target="_blank" rel="noopener">app.postly.ai</a>.
                </p>
              </td>
            </tr>
            <?php // TODO: migrate key bw_postly_workspace_id → scos_sa_postly_workspace_id — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_postly_workspace_id"><?php esc_html_e( 'Postly workspace ID', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_workspace_id</div>
              </th>
              <td>
                <input id="bw_postly_workspace_id" name="bw_postly_workspace_id" type="text"
                       class="scos-input scos-input--mono"
                       value="<?php echo esc_attr( $postly_workspace_id ); ?>">
                <p class="description"><?php esc_html_e( 'Found in the Workspace settings URL in your Postly account.', 'site-essentials' ); ?></p>
              </td>
            </tr>
          </tbody>
        </table>

        <hr style="border:none;border-top:1px solid var(--scos-border);margin:var(--scos-s-5) 0">

        <p class="scos__section-label"><?php esc_html_e( 'Social channel settings', 'site-essentials' ); ?></p>

        <table class="scos-form">
          <tbody>
            <?php // TODO: migrate key bw_postly_channel_ids → scos_sa_postly_channel_ids — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_postly_channel_ids"><?php esc_html_e( 'Target channel IDs', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_channel_ids</div>
              </th>
              <td>
                <input id="bw_postly_channel_ids" name="bw_postly_channel_ids" type="text"
                       class="scos-input scos-input--mono" placeholder="id1, id2, id3"
                       value="<?php echo esc_attr( $postly_channel_ids ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Comma-separated Postly channel IDs. Leave blank to disable standard social posting.', 'site-essentials' ); ?>
                </p>
                <div class="scos-notice scos-notice--info" style="margin-top:var(--scos-s-3)">
                  <?php esc_html_e( 'Use this field for', 'site-essentials' ); ?>
                  <strong><?php esc_html_e( 'Facebook and Instagram only.', 'site-essentials' ); ?></strong>
                  <?php esc_html_e( 'Pinterest and GMB require additional channel configuration — use the GMB field below.', 'site-essentials' ); ?>
                </div>
              </td>
            </tr>
            <?php // TODO: migrate key se_postly_gmb_channel_id → scos_sa_postly_gmb_channel_id — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="se_postly_gmb_channel_id"><?php esc_html_e( 'GMB channel ID', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_gmb_channel_id</div>
              </th>
              <td>
                <input id="se_postly_gmb_channel_id" name="se_postly_gmb_channel_id" type="text"
                       class="scos-input scos-input--mono"
                       value="<?php echo esc_attr( $postly_gmb_channel_id ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Your GMB channel ID from Postly &rarr; Channels. Only one GMB profile per workspace is supported.', 'site-essentials' ); ?>
                </p>
                <div class="scos-notice scos-notice--warning" style="margin-top:var(--scos-s-3)">
                  <strong class="scos-notice__title"><?php esc_html_e( 'In development', 'site-essentials' ); ?></strong>
                  <?php esc_html_e( 'GMB posting is functional but not yet self-service. Full setup currently requires CLI commands.', 'site-essentials' ); ?>
                  <a href="https://brighterwebsites.com.au/software/social-amplification/postly-ai-integration/"
                     target="_blank" rel="noopener"><?php esc_html_e( 'Check for updates &rarr;', 'site-essentials' ); ?></a>
                  <?php esc_html_e( 'or', 'site-essentials' ); ?>
                  <a href="https://brighterwebsites.com.au/software/social-amplification/"
                     target="_blank" rel="noopener"><?php esc_html_e( 'contact Brighter Websites', 'site-essentials' ); ?></a>
                  <?php esc_html_e( 'for assisted setup.', 'site-essentials' ); ?>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <hr style="border:none;border-top:1px solid var(--scos-border);margin:var(--scos-s-5) 0">

        <p class="scos__section-label"><?php esc_html_e( 'ACF field settings', 'site-essentials' ); ?></p>

        <table class="scos-form">
          <tbody>
            <?php // TODO: migrate key bw_social_acf_gallery_keys → scos_sa_postly_acf_gallery_keys — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_social_acf_gallery_keys"><?php esc_html_e( 'ACF gallery field keys', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_acf_gallery_keys</div>
              </th>
              <td>
                <input id="bw_social_acf_gallery_keys" name="bw_social_acf_gallery_keys"
                       type="text" class="scos-input scos-input--mono"
                       placeholder="project_gallery, secondary_gallery"
                       value="<?php echo esc_attr( $acf_gallery_keys ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Comma-separated ACF field keys containing gallery images. Combined with the featured image for post image sets.', 'site-essentials' ); ?>
                </p>
              </td>
            </tr>
            <?php // TODO: migrate key bw_social_acf_featured_key → scos_sa_postly_acf_hero_key — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="bw_social_acf_featured_key"><?php esc_html_e( 'ACF featured image key', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_acf_hero_key</div>
              </th>
              <td>
                <input id="bw_social_acf_featured_key" name="bw_social_acf_featured_key"
                       type="text" class="scos-input scos-input--mono"
                       value="<?php echo esc_attr( $acf_featured_key ); ?>">
                <p class="description">
                  <?php esc_html_e( 'Optional. Overrides the standard WordPress featured image as the first image. Leave blank to use the WP featured image.', 'site-essentials' ); ?>
                </p>
              </td>
            </tr>
            <?php // TODO: migrate key bw_social_webhook_secret → scos_sa_postly_webhook_secret — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label><?php esc_html_e( 'Webhook secret', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_postly_webhook_secret</div>
              </th>
              <td>
                <?php if ( $webhook_secret ) : ?>
                  <code class="scos-input--mono" style="font-size:var(--scos-fs-xs)"><?php echo esc_html( $webhook_secret ); ?></code>
                <?php else : ?>
                  <p style="color:var(--scos-warning);font-weight:500"><?php esc_html_e( 'Not yet generated — save settings to auto-generate.', 'site-essentials' ); ?></p>
                <?php endif; ?>
                <p class="description">
                  <?php esc_html_e( 'Auto-generated. Authenticates the internal REST endpoint (POST /wp-json/bw-social/v1/amplify). Keep private.', 'site-essentials' ); ?>
                </p>
                <input type="hidden" name="bw_social_webhook_secret" value="<?php echo esc_attr( $webhook_secret ); ?>">
              </td>
            </tr>
          </tbody>
        </table>

      </div>
      <div class="scos-card__footer">
        <button type="submit" class="scos-btn scos-btn--primary">
          <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
        </button>
      </div>
    </div><!-- /.scos-card Postly API -->

  </form>

  <?php // ── Card 3: AI knowledge documents (read-only, outside form) ─────── ?>

  <div class="scos-card">
    <div class="scos-card__header">
      <h3 class="scos-card__title"><?php esc_html_e( 'AI knowledge documents', 'site-essentials' ); ?></h3>
      <a href="https://brighterwebsites.com.au/software/social-amplification/postly-ai-integration/#ai-knowledge"
         target="_blank" rel="noopener" class="scos-badge scos-badge--soft"><?php esc_html_e( 'Guide', 'site-essentials' ); ?></a>
    </div>
    <div class="scos-card__body">

      <p class="description" style="margin-bottom:var(--scos-s-4)">
        <?php esc_html_e( 'These markdown files are read by the AI during post generation. Edit them to control brand voice, vocabulary, and per-channel posting rules. Located at', 'site-essentials' ); ?>
        <code>/wp-content/ai-knowledge/</code>
      </p>

      <div class="scos-support__grid">

        <a href="<?php echo esc_url( content_url( 'ai-knowledge/brand-core.md' ) ); ?>"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong>brand-core.md</strong>
          <span><?php esc_html_e( 'Brand voice for social media — how your posts should sound, tone, style, and personality.', 'site-essentials' ); ?></span>
        </a>

        <a href="<?php echo esc_url( content_url( 'ai-knowledge/vocabulary.md' ) ); ?>"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong>vocabulary.md</strong>
          <span><?php esc_html_e( 'Words and phrases to use and never use in generated content.', 'site-essentials' ); ?></span>
        </a>

        <a href="<?php echo esc_url( content_url( 'ai-knowledge/social-media.md' ) ); ?>"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong>social-media.md</strong>
          <span><?php esc_html_e( 'Rules for Facebook & Instagram posts — word length, hashtag and emoji usage, link inclusion.', 'site-essentials' ); ?></span>
        </a>

        <a href="<?php echo esc_url( content_url( 'ai-knowledge/social-media-gmb.md' ) ); ?>"
           target="_blank" rel="noopener" class="scos-support__tile">
          <strong>social-media-gmb.md</strong>
          <span><?php esc_html_e( 'Rules for Google Business posts — ensures GMB terms of use compliance (no contact details or URLs in body).', 'site-essentials' ); ?></span>
        </a>

      </div>

    </div>
  </div><!-- /.scos-card AI knowledge docs -->

</div><!-- /#scos-sa-panel-postly -->

<?php // ──────────────────────────────────────────────────────────────────────────── ?>
<?php // Tab 4 — Make.com settings                                                   ?>
<?php // SCOS-SA-PASS1 — single scos-card; tab slug changed from makecom to make    ?>
<?php // ──────────────────────────────────────────────────────────────────────────── ?>

<div id="scos-sa-panel-make">

  <form id="scos-sa-form-make" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'scos_sma_save', 'scos_sma_nonce' ); ?>
    <input type="hidden" name="action" value="site_essentials_save_sma">
    <input type="hidden" name="_scos_sma_tab" value="make">

    <div class="scos-card">
      <div class="scos-card__header">
        <h3 class="scos-card__title"><?php esc_html_e( 'Make.com integration', 'site-essentials' ); ?></h3>
        <a href="https://brighterwebsites.com.au/software/social-amplification/make-com-integration/"
           target="_blank" rel="noopener" class="scos-badge scos-badge--soft"><?php esc_html_e( 'Guide', 'site-essentials' ); ?></a>
      </div>
      <div class="scos-card__body">

        <p class="description" style="margin-bottom:var(--scos-s-4)">
          <?php esc_html_e( 'Configure the Make.com webhook that receives the social post trigger. The "Create Social Post" button on each post sends a payload to this URL.', 'site-essentials' ); ?>
        </p>

        <table class="scos-form">
          <tbody>

            <?php // TODO: migrate key scos_sma_webhook_url → scos_sa_make_webhook_url — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="scos_sma_webhook_url"><?php esc_html_e( 'Webhook URL', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_make_webhook_url</div>
              </th>
              <td>
                <div style="display:flex;align-items:flex-start;gap:var(--scos-s-4)">
                  <input id="scos_sma_webhook_url" name="scos_sma_webhook_url" type="url"
                         class="scos-input" placeholder="https://hook.us2.make.com/..."
                         value="<?php echo esc_attr( $make_webhook_url ); ?>"
                         style="flex:1">
                  <a href="https://brighterwebsites.com.au/software/social-amplification/social-amplification-technical-documentation/"
                     target="_blank" rel="noopener"
                     style="white-space:nowrap;font-size:var(--scos-fs-sm);color:var(--scos-accent);padding-top:8px">
                    <?php esc_html_e( 'See payload reference &rarr;', 'site-essentials' ); ?>
                  </a>
                </div>
                <p class="description"><?php esc_html_e( 'Your Make.com custom webhook URL (starts with https://hook.us2.make.com/...).', 'site-essentials' ); ?></p>
              </td>
            </tr>

            <?php // TODO: migrate key scos_sma_webhook_enabled → scos_sa_make_auto_trigger — SCOS-SA-PASS1 ?>
            <tr>
              <th>
                <label for="scos_sma_webhook_enabled"><?php esc_html_e( 'Auto-trigger on publish', 'site-essentials' ); ?></label>
                <div class="scos-form__slug">scos_sa_make_auto_trigger</div>
              </th>
              <td>
                <label class="scos-checkbox-row">
                  <input id="scos_sma_webhook_enabled" name="scos_sma_webhook_enabled" type="checkbox"
                         value="1" <?php checked( $make_auto_trigger, 1 ); ?>>
                  <?php esc_html_e( 'Automatically notify Make.com when a post is published or updated', 'site-essentials' ); ?>
                </label>
                <p class="description">
                  <em><?php esc_html_e( 'Leave off to use the manual "Create Social Post" button only — recommended for controlled social scheduling.', 'site-essentials' ); ?></em>
                </p>
              </td>
            </tr>

          </tbody>
        </table>

      </div>
      <div class="scos-card__footer">
        <button type="submit" class="scos-btn scos-btn--primary">
          <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
        </button>
      </div>
    </div><!-- /.scos-card Make.com -->

  </form>

</div><!-- /#scos-sa-panel-make -->
