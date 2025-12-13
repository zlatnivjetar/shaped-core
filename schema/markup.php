<?php
add_action('wp_head', function() {
    if (is_front_page()) {
        ?>
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "LodgingBusiness",
          "@id": "https://preelook.com/#lodging",
          "name": "Preelook Apartments & Rooms",
          "description": "Boutique coastal apartments and rooms in Rijeka, 4km from Opatija. Direct booking 10% cheaper than OTAs. Sea views, free parking, 50m from beach.",
          "url": "https://preelook.com",
          "telephone": "+385916125689",
          "email": "info@preelook.com",
          "priceRange": "€€",
          "currenciesAccepted": "EUR",
          "paymentAccepted": "Credit Card, Debit Card",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Preluk 4",
            "addressLocality": "Rijeka",
            "postalCode": "51000",
            "addressCountry": "HR"
          },
          "geo": {
            "@type": "GeoCoordinates",
            "latitude": "45.3438",
            "longitude": "14.3360"
          },
          "amenityFeature": [
            {"@type": "LocationFeatureSpecification", "name": "Free Parking", "value": true},
            {"@type": "LocationFeatureSpecification", "name": "Free WiFi", "value": true},
            {"@type": "LocationFeatureSpecification", "name": "Sea View", "value": true},
            {"@type": "LocationFeatureSpecification", "name": "Kitchen", "value": true},
            {"@type": "LocationFeatureSpecification", "name": "Air Conditioning", "value": true},
            {"@type": "LocationFeatureSpecification", "name": "Beach Access", "value": "50 meters"}
          ],
          "checkinTime": "14:00",
          "checkoutTime": "10:00",
          "petsAllowed": false,
          "numberOfRooms": 11,
          "starRating": {
            "@type": "Rating",
            "ratingValue": "4.6"
          },
          "makesOffer": [
            {
              "@type": "Offer",
              "name": "Direct Booking Discount",
              "description": "10% cheaper than OTA platforms with free cancellation until 7 days before check-in",
              "priceSpecification": {
                "@type": "PriceSpecification",
                "minPrice": "80",
                "maxPrice": "160",
                "priceCurrency": "EUR"
              }
            }
          ],
          "containsPlace": [
            {
              "@type": "Accommodation",
              "name": "Deluxe Studio Apartment",
              "floorSize": {"@type": "QuantitativeValue", "value": "55", "unitCode": "MTK"},
              "occupancy": {"@type": "QuantitativeValue", "maxValue": "4"},
              "bed": {"@type": "BedDetails", "numberOfBeds": "2", "typeOfBed": "Double bed, Sofa bed"},
              "amenityFeature": [
                {"@type": "LocationFeatureSpecification", "name": "Sea View", "value": true},
                {"@type": "LocationFeatureSpecification", "name": "Kitchen", "value": true}
              ]
            },
            {
              "@type": "Accommodation",
              "name": "Superior Studio Apartment",
              "occupancy": {"@type": "QuantitativeValue", "maxValue": "4"},
              "amenityFeature": [
                {"@type": "LocationFeatureSpecification", "name": "Kitchen", "value": true}
              ]
            },
            {
              "@type": "Accommodation",
              "name": "Standard Studio Apartment",
              "occupancy": {"@type": "QuantitativeValue", "maxValue": "4"},
              "amenityFeature": [
                {"@type": "LocationFeatureSpecification", "name": "Kitchenette", "value": true}
              ]
            },
            {
              "@type": "Accommodation",
              "name": "Deluxe Double Room",
              "occupancy": {"@type": "QuantitativeValue", "maxValue": "2"},
              "bed": {"@type": "BedDetails", "numberOfBeds": "1", "typeOfBed": "Double bed"}
            }
          ]
        }
        </script>
        <?php
    }
});