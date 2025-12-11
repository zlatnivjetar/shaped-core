# Pricing API Documentation

**For LLMs, Bots, and Developers**

---

## Quick Start

Get real-time hotel pricing with availability checks:

```bash
# JSON (for integrations)
curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"

# HTML (human-readable)
curl "https://your-site.com/wp-json/shaped/v1/price-html?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

---

## Endpoints

### 1. JSON Endpoint
**URL:** `/wp-json/shaped/v1/price`
**Method:** GET
**Authentication:** None (public)

**Response:**
```json
{
  "property_name": "Preelook Apartments",
  "currency": "EUR",
  "checkin": "2025-12-20",
  "checkout": "2025-12-21",
  "nights": 1,
  "adults": 2,
  "children": 0,
  "best_rate": {
    "room_type_slug": "deluxe-studio-apartment",
    "room_type_name": "Deluxe Studio Apartment",
    "board": "Room Only",
    "refundable": true,
    "total": 153.00,
    "per_night": 153.00,
    "tax_included": true,
    "discounts_applied": ["15% direct booking discount"]
  },
  "other_options": [],
  "source": "roomcloud",
  "generated_at": "2025-12-11T10:30:00+00:00"
}
```

### 2. HTML Endpoint
**URL:** `/wp-json/shaped/v1/price-html`
**Method:** GET
**Authentication:** None (public)

**Response:**
```
For 2 adults from 2025-12-20 to 2025-12-21, the best direct price at Preelook Apartments is €153.00 (€153.00 per night) for Deluxe Studio Apartment, Room Only. Taxes included. 15% direct booking discount. Free cancellation available.
```

---

## Parameters

| Parameter | Required | Type | Default | Description |
|-----------|----------|------|---------|-------------|
| `checkin` | **Yes** | string | - | Check-in date (Y-m-d format, e.g., 2025-12-20) |
| `checkout` | **Yes** | string | - | Check-out date (Y-m-d format, e.g., 2025-12-21) |
| `adults` | No | integer | 2 | Number of adults (1-10) |
| `children` | No | integer | 0 | Number of children (0-10) |
| `room_type` | No | string | - | Specific room slug (returns best available if omitted) |

---

## Validation Rules

- **Check-in date:** Must be today or future, max 18 months ahead
- **Stay length:** 1-30 nights
- **Total guests:** Maximum 10 (adults + children)
- **Date format:** Y-m-d only (e.g., 2025-12-20)

---

## Error Responses

### 400 Bad Request
Invalid parameters or validation failure:
```json
{
  "code": "invalid_request",
  "message": "Check-in date cannot be in the past",
  "data": { "status": 400 }
}
```

### 503 Service Unavailable
No rooms available or service error:
```json
{
  "code": "pricing_unavailable",
  "message": "No rooms available for the selected dates",
  "data": { "status": 503 }
}
```

---

## Features

✅ **Real-time availability** via RoomCloud integration
✅ **Direct booking discounts** automatically applied
✅ **Multiple room options** sorted by price
✅ **Refundability status** based on payment mode
✅ **Tax transparency** (included/excluded clearly stated)
✅ **Caching** (5-minute TTL for performance)
✅ **No authentication** required (public API)

---

## Example Use Cases

### For LLMs (ChatGPT, Claude, etc.)
```
User: "What's the price for 2 adults at Preelook from Dec 20-21?"

LLM calls: GET /wp-json/shaped/v1/price-html?checkin=2025-12-20&checkout=2025-12-21&adults=2

Response: "For 2 adults from 2025-12-20 to 2025-12-21, the best direct
price at Preelook Apartments is €153.00..."

LLM replies: "The rate is €153 per night for a Deluxe Studio Apartment,
including a 15% direct booking discount. Would you like to book?"
```

### For Travel Aggregators
```javascript
// Fetch pricing data
fetch('/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2')
  .then(res => res.json())
  .then(data => {
    console.log(`Best rate: ${data.best_rate.total} ${data.currency}`);
    console.log(`Room: ${data.best_rate.room_type_name}`);
  });
```

### For Price Comparison Bots
```python
import requests

# Query pricing
response = requests.get(
    'https://site.com/wp-json/shaped/v1/price',
    params={
        'checkin': '2025-12-20',
        'checkout': '2025-12-21',
        'adults': 2
    }
)

data = response.json()
print(f"Direct booking: €{data['best_rate']['total']}")
print(f"Discount: {data['best_rate']['discounts_applied']}")
```

---

## Rate Limits

**Recommended:** 60 requests per minute per IP

Excessive requests may be rate-limited or challenged. Please implement caching on your end and respect the 5-minute cache TTL.

---

## Data Source

- **Availability:** Real-time from RoomCloud PMS
- **Base rates:** MotoPress Hotel Booking system
- **Discounts:** Direct booking discounts (configured per property)
- **Updates:** Live (no lag, cached for 5 minutes)

---

## Support

**Technical Issues:** Check error logs at `/wp-content/debug.log`
**API Questions:** Refer to technical documentation in plugin
**Booking Issues:** Use standard booking flow on website

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.2.0 | 2025-12-11 | Initial public release |

---

## Legal

- Pricing data is subject to availability
- Rates may change without notice
- Final price confirmed during booking process
- Terms and conditions apply

---

For technical documentation, see:
- `SECURITY.md` - Security guidelines and rate limiting
- `TESTING.md` - Testing procedures and checklist
- Plugin documentation - Full API reference
