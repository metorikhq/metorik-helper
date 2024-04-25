<?php
/**
 * Metorik: Add cart email form template.
 *
 * This template can be overriden by copying this file to your-theme/metorik/add-cart-email-form.php
 *
 * Variables available:
 * 1. $title - Title (set in Metorik).
 * 2. $email_usage_notice - Email usage notice (if enabled in Metorik and customer didn't opt-out).
 *
 * The version is the most recent version of the Metorik Helper plugin this file was changed in.
 *
 * @version     1.0.4
 */
if (!defined('ABSPATH')) {
    exit;
} // Don't allow direct access
?>

<div class="metorik-add-cart-email-form">
    <!-- Title of the popup -->
    <h3><?php echo $title; ?></h3>

    <!-- Close button -->
    <div class="close-button">x</div>

    <!-- Email input wrapper - it's recommended to keep this markup as-is so loading icons can automatically be added -->
    <div class="email-input-wrapper">
        <!-- Don't change the 'email-input' class on this input - used for saving email -->
        <input type="text" placeholder="<?php _e('Your email', 'metorik'); ?>" class="email-input" />
    </div>

    <!-- Email usage notice if enabled -->
    <?php if ($email_usage_notice) {
    ?>
        <div class="email-usage-notice">
            <!-- Output email usage notice text / opt-out link -->
            <?php echo $email_usage_notice; ?>
        </div>
    <?php
} ?>
</div>