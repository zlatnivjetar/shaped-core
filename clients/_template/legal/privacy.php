<?php
/**
 * Privacy Policy Template
 *
 * Copy this file to clients/{your-client}/legal/privacy.php and customize.
 *
 * Available variables:
 * - $brand: Full brand configuration array (from brand.json)
 *
 * IMPORTANT: This template is designed for GDPR compliance (EU).
 * Consult a legal professional to ensure compliance with your jurisdiction.
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
    <strong>Template Notice:</strong> This is placeholder privacy policy content. Please customize this file for your property and jurisdiction. Consult with a legal/privacy professional to ensure GDPR compliance and compliance with local data protection laws.
</div>

<p>
    <strong>Effective Date:</strong> [INSERT DATE]<br>
    <strong>Website:</strong> <?php echo esc_html($website); ?><br>
    <strong>Legal Entity:</strong> <?php echo esc_html($legal_entity); ?><br>
    <?php if ($vat_id): ?><strong>VAT:</strong> <?php echo esc_html($vat_id); ?><br><?php endif; ?>
    <strong>Address:</strong> <?php echo esc_html($full_address); ?><br>
    <strong>Email:</strong> <?php echo esc_html($email); ?><br>
    <strong>Phone:</strong> <?php echo esc_html($phone); ?>
</p>

<p>At <?php echo esc_html($company_name); ?>, we value your privacy and are committed to protecting your personal data. This Privacy Policy explains what information we collect, why we need it, how we use it, who we share it with, and your rights.</p>

<hr />

<h2>1. Data Controller</h2>
<p>For data protection purposes, the data controller is <strong><?php echo esc_html($legal_entity); ?></strong>, located at <strong><?php echo esc_html($full_address); ?></strong>. You can contact us about privacy matters at <strong><?php echo esc_html($email); ?></strong>.</p>

<p>[INSERT YOUR LOCAL DATA PROTECTION AUTHORITY INFORMATION HERE]</p>

<hr />

<h2>2. Information We Collect</h2>

<h3>2.1 Online Booking Information</h3>
<p>When you make a reservation through our website, we collect:</p>
<ul>
    <li><strong>Contact details:</strong> Full name, email address, phone number</li>
    <li><strong>Booking details:</strong> Check-in/out dates, accommodation type, number of guests, special requests</li>
    <li><strong>Billing information:</strong> ZIP/postal code (for payment processing)</li>
</ul>

<h3>2.2 Check-in Registration</h3>
<p>[CUSTOMIZE BASED ON YOUR LOCAL LEGAL REQUIREMENTS - e.g., guest registration systems required by law]</p>

<h3>2.3 Payment Information</h3>
<p>We process payments through Stripe's secure hosted checkout. We never store your full card details on our servers. Stripe handles all payment data according to PCI DSS security standards. Learn more: <a href="https://stripe.com/privacy">stripe.com/privacy</a></p>

<h3>2.4 Website Analytics</h3>
<p>We use:</p>
<ul>
    <li><strong>Essential cookies:</strong> Required for website functionality</li>
    <li><strong>Analytics cookies:</strong> With your consent only, to improve our services</li>
</ul>

<hr />

<h2>3. Legal Basis for Processing</h2>
<p>We process your data based on:</p>
<ul>
    <li><strong>Contract:</strong> To fulfill your booking and provide accommodation services</li>
    <li><strong>Legal Obligation:</strong> To comply with local guest registration and tax requirements</li>
    <li><strong>Legitimate Interests:</strong> For service improvements and security</li>
    <li><strong>Consent:</strong> For marketing communications and non-essential cookies</li>
</ul>

<hr />

<h2>4. How We Use Your Data</h2>
<p>We use your information to:</p>
<ul>
    <li>Process and manage your reservation</li>
    <li>Send booking confirmations and pre-arrival information</li>
    <li>Handle payments and refunds</li>
    <li>Comply with legal requirements</li>
    <li>Provide customer support</li>
    <li>With your consent: Send promotional offers</li>
</ul>

<hr />

<h2>5. Data Sharing</h2>
<p>We never sell your personal data. We share information only when necessary:</p>

<h3>Service Providers</h3>
<ul>
    <li><strong>Website & Booking System:</strong> WordPress with MotoPress Hotel Booking</li>
    <li><strong>Payment Processing:</strong> Stripe</li>
    <li><strong>Hosting:</strong> [YOUR HOSTING PROVIDER]</li>
</ul>

<h3>Legal Requirements</h3>
<ul>
    <li>Local authorities as required by law</li>
    <li>Tax authorities for fiscal compliance</li>
</ul>

<hr />

<h2>6. Data Retention</h2>
<p>We retain your data for:</p>
<ul>
    <li><strong>Guest registration records:</strong> [X] years (as required by law)</li>
    <li><strong>Financial records:</strong> [X] years (accounting regulations)</li>
    <li><strong>Marketing data:</strong> Until you unsubscribe</li>
</ul>

<hr />

<h2>7. Your Privacy Rights</h2>
<p>You have the right to:</p>
<ul>
    <li><strong>Access</strong> your personal data</li>
    <li><strong>Rectify</strong> inaccurate information</li>
    <li><strong>Erase</strong> your data (where legally permitted)</li>
    <li><strong>Restrict</strong> processing in certain circumstances</li>
    <li><strong>Data portability</strong></li>
    <li><strong>Object</strong> to certain types of processing</li>
    <li><strong>Withdraw consent</strong> at any time</li>
</ul>

<p>To exercise these rights, contact us at <strong><?php echo esc_html($email); ?></strong>. We'll respond within 30 days.</p>

<hr />

<h2>8. Security Measures</h2>
<p>We protect your data through:</p>
<ul>
    <li>SSL/TLS encryption across our entire website</li>
    <li>Secure payment processing via PCI-compliant providers</li>
    <li>Access controls limiting data access to authorized personnel</li>
</ul>

<hr />

<h2>9. Updates to This Policy</h2>
<p>We may update this policy to reflect changes in law or our practices. Significant changes will be communicated via our website.</p>

<hr />

<h2>10. Contact Us</h2>
<p>For any privacy-related questions:</p>
<p>
    <strong><?php echo esc_html($legal_entity); ?></strong><br>
    <?php echo esc_html($full_address); ?><br>
    <strong>Email:</strong> <?php echo esc_html($email); ?><br>
    <strong>Phone:</strong> <?php echo esc_html($phone); ?>
</p>

<hr />
<p><em>This policy was last updated on [INSERT DATE]</em></p>
