<?php
defined( 'ABSPATH' ) || exit;
?>
<p><?php esc_html_e( 'Social Amplification settings (webhook URL, YOURLS API) are managed in the legacy Social Amplification admin page.', 'site-essentials' ); ?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=bw-social-amplification' ) ); ?>"><?php esc_html_e( 'Open settings', 'site-essentials' ); ?></a></p>
