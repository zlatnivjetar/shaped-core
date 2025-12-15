<?php
/**
 * Terms & Conditions Template
 *
 * Copy this file to clients/{your-client}/legal/terms.php and customize.
 *
 * Available variables:
 * - $brand: Full brand configuration array (from brand.json)
 *
 * Access brand values like:
 * - $brand['company']['name']
 * - $brand['contact']['email']
 * - $brand['contact']['address']['street']
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract commonly used values from brand config
$company_name = $brand['company']['name'] ?? 'Property Name';
$legal_entity = $brand['company']['legalEntity'] ?? 'Legal Entity';
$vat_id = $brand['company']['vatId'] ?? '';
$jurisdiction = $brand['company']['jurisdiction'] ?? 'Country';
$address = $brand['contact']['address'] ?? [];
$full_address = implode(', ', array_filter([
    $address['street'] ?? '',
    $address['postalCode'] ?? '',
    $address['city'] ?? '',
    $address['country'] ?? ''
]));
$email = $brand['contact']['email'] ?? '';
$phone = $brand['contact']['phone'] ?? '';
$website = str_replace(['https://', 'http://'], '', home_url());
?>

<div class="legal-notice" style="background: #FEF3C7; border: 1px solid #F59E0B; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
    <strong>Template Notice:</strong> This is placeholder legal content. Please customize this file for your property's specific terms and conditions. Consult with a legal professional to ensure compliance with local laws.
</div>

<em>Effective Date: [INSERT DATE]</em>

<h2>1. Agreement</h2>
<p>These Terms and Conditions constitute a legally binding agreement between <strong><?php echo esc_html($legal_entity); ?></strong><?php if ($vat_id): ?>, VAT ID: <?php echo esc_html($vat_id); ?><?php endif; ?>, with registered address at <?php echo esc_html($full_address); ?> ("<strong><?php echo esc_html($company_name); ?></strong>", "we", "us", "our") and the guest making the reservation ("Guest", "you", "your").</p>

<p>By completing a booking, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</p>

<h2>2. Booking and Confirmation</h2>
<h3>2.1 Reservation Process</h3>
<ul>
    <li>All reservations are subject to availability and confirmation</li>
    <li>A binding contract exists upon receipt of booking confirmation via email</li>
    <li>Minimum booking age: 18 years</li>
    <li>Valid government-issued photo ID required at check-in</li>
</ul>

<h3>2.2 Booking Guarantee</h3>
<ul>
    <li>Payment terms: [CUSTOMIZE - e.g., "Full payment at booking" or "Payment card saved, charged before arrival"]</li>
    <li>Payment processed via our secure payment processor (Stripe)</li>
    <li>All rates quoted in [CURRENCY]</li>
    <li>Rates include [CUSTOMIZE - e.g., "VAT and local taxes"]</li>
</ul>

<h2>3. Cancellation and Refund Policy</h2>
<h3>3.1 Guest Cancellations</h3>
<ul>
    <li>[X] days or more before arrival: [POLICY]</li>
    <li>Less than [X] days before arrival: [POLICY]</li>
    <li>No-show: [POLICY]</li>
</ul>

<h3>3.2 Property Cancellations</h3>
<ul>
    <li>Full refund provided if we cancel your reservation</li>
    <li>Alternative accommodation offered when possible</li>
    <li>Compensation limited to refund of amounts paid</li>
</ul>

<h2>4. Check-in and Check-out</h2>
<h3>4.1 Arrival</h3>
<ul>
    <li>Check-in time: [TIME RANGE]</li>
    <li>Late check-in: [POLICY]</li>
</ul>

<h3>4.2 Departure</h3>
<ul>
    <li>Check-out time: [TIME]</li>
    <li>Late check-out: [POLICY]</li>
</ul>

<h2>5. Guest Responsibilities</h2>
<h3>5.1 Property Care</h3>
<ul>
    <li>Maintain property in good condition</li>
    <li>Report damages immediately</li>
    <li>Liable for damages beyond normal wear and tear</li>
</ul>

<h3>5.2 House Rules</h3>
<ul>
    <li>Smoking: [POLICY]</li>
    <li>Pets: [POLICY]</li>
    <li>Parties/events: [POLICY]</li>
    <li>Quiet hours: [TIMES]</li>
</ul>

<h2>6. Liability</h2>
<ul>
    <li>Not responsible for loss, theft, or damage to personal property</li>
    <li>Maximum liability limited to accommodation cost</li>
    <li>Guests advised to obtain appropriate travel insurance</li>
</ul>

<h2>7. Privacy</h2>
<p>Personal data collected and processed per our Privacy Policy and applicable data protection laws. See our full Privacy Policy for details.</p>

<h2>8. Disputes</h2>
<ul>
    <li>Governed by the laws of <?php echo esc_html($jurisdiction); ?></li>
    <li>Written complaints: <?php echo esc_html($email); ?></li>
</ul>

<h2>9. Contact Information</h2>
<p>
    <strong><?php echo esc_html($company_name); ?></strong><br>
    <?php echo esc_html($full_address); ?><br>
    Phone: <?php echo esc_html($phone); ?><br>
    Email: <?php echo esc_html($email); ?><br>
    Website: <?php echo esc_html($website); ?>
</p>

<hr />
<p><em>By completing your booking, you confirm that you have read, understood, and agree to these Terms and Conditions.</em></p>
<p><em>Last updated: [INSERT DATE]</em></p>
