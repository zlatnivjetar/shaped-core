<?php
/**
 * Privacy Policy - Preelook Apartments
 *
 * This file contains the property-specific Privacy Policy.
 * Variables available: $brand (full brand config array)
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

$company_name = $brand['company']['name'] ?? 'Property Name';
$legal_entity = $brand['company']['legalEntity'] ?? 'Legal Entity';
$vat_id = $brand['company']['vatId'] ?? '';
$address = $brand['contact']['address'] ?? [];
$full_address = ($address['street'] ?? '') . ', ' . ($address['postalCode'] ?? '') . ', ' . ($address['city'] ?? '') . ', ' . ($address['country'] ?? '');
$email = $brand['contact']['email'] ?? '';
$phone = $brand['contact']['phone'] ?? '';
$website = str_replace(['https://', 'http://'], '', home_url());
?>
<strong>Effective Date:</strong> 09.09.2025 <br>
<strong>Website:</strong> <?php echo esc_html($website); ?> <br>
<strong>Legal Entity:</strong> <?php echo esc_html($legal_entity); ?> <br>
<strong>VAT:</strong> <?php echo esc_html($vat_id); ?> <br>
<strong>Address:</strong> <?php echo esc_html($full_address); ?> <br>
<strong>Email:</strong> <?php echo esc_html($email); ?> <br>
<strong>Phone:</strong> <?php echo esc_html($phone); ?> <br><br>

At <?php echo esc_html($company_name); ?>, we value your privacy and are committed to protecting your personal data. This Privacy Policy explains what information we collect, why we need it, how we use it, who we share it with, and your rights under the General Data Protection Regulation (GDPR).

<hr />

<h2>1. Data Controller</h2>
For GDPR purposes, the data controller is <strong><?php echo esc_html($legal_entity); ?></strong>, located at <strong><?php echo esc_html($full_address); ?></strong>. You can contact us about privacy matters at <strong><?php echo esc_html($email); ?></strong>. <br><br>

The Croatian Data Protection Authority (AZOP - Agencija za zaštitu osobnih podataka) supervises data protection compliance. You may contact AZOP if you believe your privacy rights have been violated: <a href="https://azop.hr/">azop.hr</a><br>

<hr />

<h2>2. Information We Collect</h2>
<h3>2.1 Online Booking Information</h3>
When you make a reservation through our website, we collect:
<ul>
 	<li><strong>Contact details:</strong> Full name, email address, phone number</li>
 	<li><strong>Booking details:</strong> Check-in/out dates, accommodation type, number of guests (adults/children), special requests</li>
 	<li><strong>Billing information:</strong> ZIP/postal code (for payment processing)</li>
</ul>
<h3>2.2 Check-in Registration (eVisitor)</h3>
Upon arrival at our property, Croatian law requires us to collect and register additional guest information in the eVisitor system:
<ul>
 	<li>Full name, date and place of birth, citizenship, permanent address</li>
 	<li>ID document type and number, gender</li>
 	<li>Arrival/departure dates, sojourn tax exemptions (if applicable)</li>
</ul>
This data collection at check-in is mandatory under Croatian tourism regulations. <br><br>
<h3>2.3 Payment Information</h3>
We process payments through Stripe's secure hosted checkout. We never store your full card details on our servers. Stripe handles all payment data according to PCI DSS security standards. Learn more: <a href="https://stripe.com/privacy"><u>stripe.com/privacy</u></a> <br><br>
<h3>2.4 Communications</h3>
If you opt in, we collect:
<ul>
 	<li>Email engagement data for service messages (booking confirmations, check-in instructions)</li>
 	<li>Marketing preferences for optional promotional communications</li>
</ul>
<h3>2.5 Website Analytics</h3>
We use:
<ul>
 	<li><strong>Essential cookies:</strong> Required for website functionality</li>
 	<li><strong>Analytics cookies:</strong> With your consent only, to improve our services and measure website performance</li>
</ul>

<hr />

<h2>3. Legal Basis for Processing</h2>
We process your data based on:
<ul>
 	<li><strong>Contract (Article 6(1)(b) GDPR):</strong> To fulfill your booking, provide accommodation services, and manage your reservation</li>
 	<li><strong>Legal Obligation (Article 6(1)(c) GDPR):</strong> To comply with Croatian guest registration requirements (eVisitor), tax regulations, and accounting laws</li>
 	<li><strong>Legitimate Interests (Article 6(1)(f) GDPR):</strong> For service improvements, fraud prevention, and security (balanced against your rights)</li>
 	<li><strong>Consent (Article 6(1)(a) GDPR):</strong> For marketing communications and non-essential cookies</li>
</ul>

<hr />

<h2>4. How We Use Your Data</h2>
We use your information to:
<ul>
 	<li>Process and manage your reservation</li>
 	<li>Send booking confirmations and pre-arrival information</li>
 	<li>Handle payments and refunds through our payment processor</li>
 	<li>Register guests with Croatian authorities as legally required (at check-in)</li>
 	<li>Provide customer support and respond to inquiries</li>
 	<li>With your consent: Send promotional offers and analyze website usage to improve our services</li>
</ul>

<hr />

<h2>5. Data Sharing</h2>
We never sell your personal data. We share information only when necessary to provide services or meet legal requirements:
<h3>Service Providers</h3>
<ul>
 	<li><strong>Website &amp; Booking System:</strong> WordPress with MotoPress Hotel Booking</li>
 	<li><strong>Payment Processing:</strong> Stripe Payments Europe Ltd. for secure payment handling</li>
 	<li><strong>Hosting:</strong> Hostinger</li>
 	<li><b>Analytics: </b>Google Analytics</li>
</ul>
<h3>Legal Requirements</h3>
<ul>
 	<li>Croatian tourist authorities via eVisitor system (for check-in data)</li>
 	<li>Tax authorities for fiscal compliance</li>
 	<li>Law enforcement or courts when legally required</li>
</ul>

<hr />

<h2>6. International Data Transfers</h2>
Some service providers may process data outside the European Economic Area. Where this occurs, we ensure appropriate safeguards:
<ul>
 	<li>EU-US Data Privacy Framework (for certified providers like Mailchimp)</li>
 	<li>Standard Contractual Clauses with supplementary measures</li>
 	<li>Adequacy decisions by the European Commission</li>
</ul>

<hr />

<h2>7. Data Retention</h2>
We retain your data for:
<ul>
 	<li><strong>Guest registration records (eVisitor):</strong> 10 years (Croatian legal requirement)</li>
 	<li><strong>Financial records:</strong> 11 years (Croatian accounting regulations)</li>
 	<li><strong>Booking correspondence:</strong> 5 years for potential disputes</li>
 	<li><strong>Marketing data:</strong> Until you unsubscribe or after 24 months of inactivity</li>
</ul>
We delete or anonymize data when retention periods expire, unless longer retention is required by law.

<hr />

<h2>8. Your Privacy Rights</h2>
Under GDPR, you have the right to:
<ul>
 	<li><strong>Access</strong> your personal data we hold</li>
 	<li><strong>Rectify</strong> inaccurate information</li>
 	<li><strong>Erase</strong> your data (where legally permitted)</li>
 	<li><strong>Restrict</strong> processing in certain circumstances</li>
 	<li><strong>Data portability</strong> to transfer your data</li>
 	<li><strong>Object</strong> to certain types of processing</li>
 	<li><strong>Withdraw consent</strong> at any time (for consent-based processing)</li>
 	<li><strong>Lodge a complaint</strong> with AZOP</li>
</ul>
To exercise these rights, contact us at <strong><?php echo esc_html($email); ?></strong>. We'll respond within 30 days.

<hr />

<h2>9. Cookie Policy</h2>
<h3>Essential Cookies</h3>
Required for website operation (booking process, security, session management). These cannot be disabled.
<h3>Optional Cookies</h3>
Analytics and marketing cookies are only set after you provide consent. You can manage your preferences anytime via our cookie consent tool.

<hr />

<h2>10. Security Measures</h2>
We protect your data through:
<ul>
 	<li>SSL/TLS encryption across our entire website</li>
 	<li>Secure payment processing via PCI-compliant providers</li>
 	<li>Access controls limiting data access to authorized personnel</li>
 	<li>Regular security assessments and updates</li>
</ul>

<hr />

<h2>11. Children's Privacy</h2>
Our services are available to families traveling with children. For guests under 16 years old, bookings should be made by a parent or guardian. We collect children's data only as required for accommodation services and legal compliance.

<hr />

<h2>12. Third-Party Bookings</h2>
If you book through an online travel agency (OTA), they act as an independent data controller. We receive only the information necessary to fulfill your reservation and meet legal obligations. OTA bookings are also subject to the OTA's privacy policy.

<hr />

<h2>13. Additional Information</h2>
<h3>Data Protection Officer</h3>
We are not required to appoint a DPO under GDPR. For privacy matters, please contact us at <?php echo esc_html($email); ?>.

<hr />

<h2>14. Updates to This Policy</h2>
We may update this policy to reflect changes in law or our practices. Significant changes will be communicated via our website with an updated effective date.

<hr />

<h2>15. Contact Us</h2>
For any privacy-related questions or to exercise your rights: <br>

<strong><?php echo esc_html($legal_entity); ?></strong> <br>
<strong><?php echo esc_html($full_address); ?></strong> <br>
<strong>Email:</strong> <?php echo esc_html($email); ?> <br>
<strong>Phone:</strong> <?php echo esc_html($phone); ?> <br>

<hr />
<br>
<em>This policy was last updated on 09.09.2025</em>
