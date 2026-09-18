<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// This mirrors WooCommerce's own myaccount/form-login.php register column
// field-for-field (same names/ids/nonce) so WC_Form_Handler::process_registration()
// — which is hooked globally on 'wp_loaded', not tied to any one page —
// picks this up exactly like the default combined login/register page would.
if (function_exists('wc_print_notices')) {
    wc_print_notices();
}
?>
<div class="angebot-auth-page">
    <form method="post" class="woocommerce-form woocommerce-form-register register" action="">
        <?php do_action('woocommerce_register_form_start'); ?>

        <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_username"><?php esc_html_e('Username', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo (!empty($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>">
            </p>
        <?php endif; ?>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="reg_email"><?php esc_html_e('Email address', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo (!empty($_POST['email'])) ? esc_attr(wp_unslash($_POST['email'])) : ''; ?>">
        </p>

        <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_password"><?php esc_html_e('Password', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password">
            </p>
        <?php else : ?>
            <p class="angebot-reg-note"><?php esc_html_e('A link to set a new password will be sent to your email address.', 'woocommerce'); ?></p>
        <?php endif; ?>

        <?php do_action('woocommerce_register_form'); ?>

        <p class="woocommerce-form-row form-row">
            <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
            <button type="submit" class="angebot-btn angebot-btn--primary woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>"><?php esc_html_e('Register', 'woocommerce'); ?></button>
        </p>

        <?php do_action('woocommerce_register_form_end'); ?>
    </form>

    <p class="angebot-auth-switch">
        <?php esc_html_e('Already have an account?', 'angebot-deals'); ?>
        <a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/')); ?>"><?php esc_html_e('Log in', 'angebot-deals'); ?></a>
    </p>
</div>
