<?php
/**
 * Terms & Conditions - Preelook Apartments
 *
 * This file contains the property-specific Terms & Conditions.
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
?>
<em>Effective Date: 09.09.2025</em>
<h2>1. Agreement</h2>
These Terms and Conditions constitute a legally binding agreement between <?php echo esc_html($legal_entity); ?>, VAT ID: <?php echo esc_html($vat_id); ?>, with registered address at <?php echo esc_html($full_address); ?> ("<?php echo esc_html($company_name); ?>", "we", "us", "our") and the guest making the reservation ("Guest", "you", "your").

By completing a booking, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.<br>
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
 	<li>For bookings made more than 7 days before check-in: Payment card details securely saved at booking, charged 7 days before arrival</li>
 	<li>For bookings made within 7 days of check-in: Full payment required at time of booking</li>
 	<li>Payment processed via our secure payment processor (Stripe)</li>
 	<li>All rates quoted in EUR</li>
 	<li>Rates include VAT and tourist tax</li>
</ul>
<h3>2.3 Occupancy Limits</h3>
<ul>
 	<li>Maximum occupancy per apartment type strictly enforced</li>
 	<li>Studio Apartment: Maximum 4 adults, 2 children</li>
 	<li>Double Room: Maximum 2 adults, 1 child</li>
 	<li>Children over 3 years of age are treated as adults for occupancy purposes</li>
</ul>
<h2>3. Cancellation and Refund Policy</h2>
<h3>3.1 Guest Cancellations</h3>
<ul>
 	<li>7 days or more before arrival: No charge, free cancellation</li>
 	<li>Less than 7 days before arrival: No refund available</li>
 	<li>No-show: No refund</li>
 	<li>For bookings made more than 7 days in advance, payment is processed 7 days before check-in</li>
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
 	<li>Check-in time: 16:00 to 22:00</li>
 	<li>Late check-in: Available with 48 hours prior notice</li>
</ul>
<h3>4.2 Departure</h3>
<ul>
 	<li>Check-out time: 08:00 to 11:00</li>
 	<li>Failure to vacate by required time: Full day rate charged</li>
</ul>
<h2>5. Guest Responsibilities and Conduct</h2>
<h3>5.1 Property Care</h3>
<ul>
 	<li>Maintain apartment in good condition</li>
 	<li>Report damages immediately</li>
 	<li>Liable for damages beyond normal wear and tear</li>
 	<li>Security deposit: 100 EUR</li>
</ul>
<h3>5.2 House Rules</h3>
<ul>
 	<li>Smoking: Prohibited in all indoor areas</li>
 	<li>Pets: Allowed up to 3kg</li>
 	<li>Parties/events: Not permitted</li>
 	<li>Quiet hours: 22:00 to 08:00</li>
</ul>
<h3>5.3 Prohibited Activities</h3>
<ul>
 	<li>Illegal activities strictly prohibited</li>
 	<li>Commercial activities without written permission</li>
 	<li>Subletting or unauthorized occupancy</li>
 	<li>Disturbing other guests or neighbors</li>
</ul>
<h2>6. Liability and Indemnification</h2>
<h3>6.1 Limitation of Liability</h3>
<ul>
 	<li>Not responsible for loss, theft, or damage to personal property</li>
 	<li>Recommend using in-room safe for valuables</li>
 	<li>Maximum liability limited to accommodation cost</li>
 	<li>No liability for indirect, consequential, or punitive damages</li>
</ul>
<h3>6.2 Guest Indemnification</h3>
<ul>
 	<li>You agree to indemnify and hold harmless <?php echo esc_html($company_name); ?> from all claims, damages, losses, and expenses arising from your breach of these terms or negligent/wrongful acts</li>
</ul>
<h3>6.3 Insurance</h3>
<ul>
 	<li>Guests advised to obtain appropriate travel insurance</li>
 	<li>Property insurance does not cover guest belongings</li>
</ul>
<h2>7. Privacy and Data Protection</h2>
<h3>7.1 Data Collection</h3>
<ul>
 	<li>Personal data collected and processed per applicable data protection laws</li>
 	<li>Data used for booking management, legal compliance, and service improvement</li>
 	<li>Marketing communications only with explicit consent</li>
</ul>
<h3>7.2 Data Sharing</h3>
<ul>
 	<li>Shared with necessary service providers (payment processing, housekeeping)</li>
 	<li>Disclosed when required by law or authorities</li>
 	<li>Never sold to third parties</li>
</ul>
<h3>7.3 Data Retention</h3>
<ul>
 	<li>Booking data retained for 12 months for legal and tax purposes</li>
 	<li>Right to request data access, correction, or deletion per GDPR</li>
</ul>
<h2>8. Force Majeure</h2>
Neither party liable for failure to perform obligations due to circumstances beyond reasonable control, including but not limited to:
<ul>
 	<li>Natural disasters, extreme weather</li>
 	<li>War, terrorism, civil unrest</li>
 	<li>Government actions, travel restrictions</li>
 	<li>Pandemic or epidemic</li>
 	<li>Utility failures beyond our control</li>
</ul>
<h2>9. Complaints and Disputes</h2>
<h3>9.1 Complaint Procedure</h3>
<ul>
 	<li>Report issues immediately to reception/management</li>
 	<li>Written complaints: <?php echo esc_html($email); ?></li>
 	<li>Response within 72 hours</li>
</ul>
<h3>9.2 Applicable Law</h3>
<ul>
 	<li>Governed by the laws of the Republic of Croatia</li>
 	<li>Disputes subject to exclusive jurisdiction of Municipal Court of Rijeka</li>
</ul>
<h3>9.3 Alternative Dispute Resolution</h3>
<ul>
 	<li>Good faith attempt to resolve disputes before legal action</li>
 	<li>EU residents may use EU Online Dispute Resolution platform</li>
</ul>
<h2>10. Special Provisions</h2>
<h3>10.1 Accessibility</h3>
<ul>
 	<li><strong>[ACCESSIBILITY INFORMATION]</strong></li>
 	<li>Special requirements must be communicated at booking</li>
</ul>
<h3>10.2 Children</h3>
<ul>
 	<li>Children under 18 must be supervised at all times</li>
 	<li>Baby cots available free upon request</li>
</ul>
<h3>10.3 Parking</h3>
<ul>
 	<li>Free private parking provided for all guests</li>
 	<li>Not responsible for vehicle damage or theft</li>
</ul>
<h2>11. General Terms</h2>
<h3>11.1 Entire Agreement</h3>
<ul>
 	<li>These terms constitute the entire agreement</li>
 	<li>Supersede all prior agreements or understandings</li>
</ul>
<h3>11.2 Severability</h3>
<ul>
 	<li>Invalid provisions shall not affect remaining terms</li>
 	<li>Invalid provisions replaced with enforceable alternatives</li>
</ul>
<h3>11.3 Amendments</h3>
<ul>
 	<li>We reserve the right to modify these terms</li>
 	<li>Existing bookings governed by terms at time of booking</li>
</ul>
<h3>11.4 Contact Information</h3>
<strong><?php echo esc_html($company_name); ?></strong><br>
<?php echo esc_html($full_address); ?>
<br>
Phone: <?php echo esc_html($phone); ?>
<br>
Email: <?php echo esc_html($email); ?>
<br>
Website: <?php echo esc_html(str_replace(['https://', 'http://'], '', home_url())); ?>
<h3>11.5 Emergency Contacts</h3>
Emergency Services: 112<br>
Property Emergency: <?php echo esc_html($phone); ?>

<hr />
<br>
<em>By completing your booking, you confirm that you have read, understood, and agree to these Terms and Conditions.</em>
<br><br>
<em>Last updated: 09.09.2025</em>
