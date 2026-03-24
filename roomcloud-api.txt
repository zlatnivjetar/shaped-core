RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 1 / 21
RoomCloud XML OTA API
Version 3.8
05/04/2024
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 2 / 21
REVISION HISTORY
Version Description of the revision
3.8 Removed deprecated attribute in reservation message
Added attribute pin and offer element in reservation message
3.7
Added childAge element
3.6 Changes to supplement element
Added lang attribute to reservation element
3.5
Attribute source_of_business inside reservation tag
3.4 Errata corrige:
• tag rate attribute id changed to rateId in getRates response
• tag supplement attribute price in reservations response
• tag room attribute price in reservation response
3.3
General review
3,2
Errata corrige
3.1
Corrected 1.3. Request message structure and access credentials
3.0
First release
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 3 / 21
1. INTRODUCTION........................................................................................................................4
1.1 API goal.................................................................................................................................4
1.2 Data Flow ..............................................................................................................................4
1.3 Data Exchange Technology...................................................................................................4
1.4 Request message structure and access credentials ................................................................4
1.5 Response Message structure..................................................................................................4
1.6 Error Message........................................................................................................................5
1.7 Messages ...............................................................................................................................5
2. Configuration messages................................................................................................................5
2.1 getHotels................................................................................................................................5
2.2 getRates.................................................................................................................................6
2.3 getRooms...............................................................................................................................6
3. Read and Update Inventory ..........................................................................................................8
3.1 View.......................................................................................................................................8
3.2 Modify.................................................................................................................................10
4. Reservations................................................................................................................................13
5. Error Handling ............................................................................................................................19
5.1 Error codes ..........................................................................................................................19
6. Roomcloud Inventory .................................................................................................................20
6.1 Roomcloud Data Structure ..................................................................................................20
6.2 The Roomcloud Inventory...................................................................................................20
7. Testing.........................................................................................................................................21
7.1 Test Page..............................................................................................................................21
7.2 Roomcloud Test Account ....................................................................................................21
7.3 Contacts...............................................................................................................................21
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 4 / 21
1. INTRODUCTION
1.1 API goal
The purpose of this document is to provide Online Travel Agencies or other Booking Engine
Systems (from now on referred as OTA) the informations needed to build the integration, via
an XML-based API, with RoomCloud Channel Manager.
The main purpose of the integration is
1. to send rates and availabilities updates from Roomcloud to the OTA
2. to receive into Roomcloud new/modified/cancelled reservations from the OTA
1.2 Data Flow
The data flow is then the following
1. Availabilities and rates updates
RoomCloud Channel Manager → OTA
2. Reservations
OTA → RoomCloud Channel Manager
Every time that a user updates the Roomcloud inventory, Roomcloud simultanously pushes the
updated values to the OTA
Availabilities are automatically recalculated and sent to all the connected channels, every time
that Roomcloud receives a new reservation from the OTA
1.3 Data Exchange Technology
Roomcloud uses a REST client to send XML messages to the OTA inside the body of HTTP requests.
The OTA must provide a unique url where Roomcloud will send all the XML calls. This url is
referred as the service_url
1.4 Request message structure and access credentials
Every message is enclosed in a “Request" element that contains the access credentials
(userName and password attributes provided by the OTA):
<?xml version="1.0" encoding="UTF-8"?>
<Request userName="MyLogin" password="MyPassword" echoToken=”xxyyzzhh”>
<message>-----</message>
</Request>
Username and password could be those used by the property to access the OTA extranet but, to
avoid confusion and misunderstanding, it’s better to have credentials reserved for the API usage.
1.5 Response Message structure
Following the basic response message structure is displayed:
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 5 / 21
<?xml version="1.0" encoding="UTF-8"?>
<Response echoToken=”xxyyzzhh”>
<responseMessage>-----</responseMessage>
</Response>
1.6 Error Message
Error messages are returned as attribute of an error element.
<Response echoToken=”xxyyzzhh”>
<error message="Authentication Failed - Wrong Password" code=”2100” />
</Response>
1.7 Messages
Messages are:
1. getHotels : Roomcloud sends this message to retrieve from the channel the hotel IDs
associated to the provided credentials
2. getRates : Roomcloud sends this message to retrieve from the channel the mapping
codes corresponding to the rateplans of a hotel
3. getRooms : Roomcloud sends this message to retrieve from the channel the mapping
codes corresponding to the roomtypes of a hotel
4. reservations: Roomcloud sends this message to periodically retrieve from the channel
new/modified/cancelled reservations of a hotel
5. view: Roomcloud sends this message to read from the channel availabilities, rates and
restrictions for the roomtypes and rateplans of an hotel
6. modify : Roomcloud sends this message to push to the channel availabilities, rates and
restrictions for the roomtypes and rateplans of a hotel
2. Configuration messages
2.1 getHotels
This method returns to Roomcloud the list of all hotels associated to an username/password.
Ex:
<Request userName="MyLogin" password="MyPassword">
 <getHotels/>
</Request>
<Response>
 <hotel id="324" description="Hotel RoomCloud Milano" />
 <hotel id="456" description="Hotel RoomCloud Roma" />
</Response>
Request elements
Level Element/attribute Type Mandatory Description
1 getHotels
Response elements
Level Element/attribute Type Mandatory Description
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 6 / 21
1 hotel
@id String Y Unique identifier of the hotel
@description String Y hotel name/ description
2.2 getRates
It gives the list of rates for a selected hotel. Each element is characterized by two attributes,
rateId and description both required.
Ex:
<Request userName="MyLogin" password="MyPassword">
 <getRates hotelId="324"/>
</Request>
<Response>
 <rate rateId="934" description="std" />
 <rate rateId="1452" description="pkg" />
</Response>
Request elements
Level Element/attribute Type Mandatory Description
1 getRates
@hotelId String Y
Response elements
Level Element/attribute Type Mandatory Description
1 rate
@rateId String Y Unique identifier of the rateplan
@description String Y hotel name/ description
2.3 getRooms
It returns the list of room types for the selected hotel.
Every roomtype contains the list of the associated rateplans and the list of the accepted extra
adult and extra child supplements.
Ex:
<Request userName="MyLogin" password="MyPassword">
<getRooms hotelId="324"/>
</Request>
<Response>
<room id="2092" baseOccupancy="2" additionalBeds="0" description="Double">
 <rate rateId="934" />
<rate rateId="1452" />
</room>
<room id="3384" baseOccupancy="2" additionalBeds="2" description="Suite">
<rate rateId="934" />
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 7 / 21
 <occupancy rateId="934" id="1" beds="1" />
 <adult rateId="934" id="3" additionalBeds="1" />
 <adult rateId="934" id="4" additionalBeds="2" />
 <child rateId="934" id="0" from="2" to="7" additionalBeds="1" />
 <child rateId="934" id="1" from="2" to="7" additionalBeds="2" />
 <rate rateId="935" />
 <occupancy rateId="1452" id="1" beds="1" />
<adult rateId="1452" id="3" additionalBeds="1" />
<child rateId="1452" id="0" from="2" to="7" additionalBeds="1" />
</room>
</Response>
Request elements
Level Element/attribute Type Mandatory Description
1 getRooms
@hotelId String Y
Response elements
Level Element/attribute Type Mandatory Description
1 room
@id String Y
Unique identifier of the roomtype
@description String Y
Roomtype name/ description
@baseOccupancy Integer Roomtype base occupancy
@additionalBeds integer max additional beds available for the room
2 Rate
@rateId String Y
2 occupancy Occupancy based prices
@id String Y
@rateId String Y
@occupancy Integer Y
Number of guests
2 adult Extra adult supplements
@id
@rateId
@additionalBeds First extraadult, second extraadult ecc
2 child Extra child supplements
@id String
@rateId String
@from Integer starting age of child
@to Integer ending age of child
@additionalBeds Integer First extra child, second extra child ecc
Remarks
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 8 / 21
You must return the extrachild and extradult elements only if it is possible to send supplement
prices on a daily basis. If these supplements have fixed price amounts in your system you must
not add them to the getRooms response
<room id="3384" baseOccupancy="2" additionalBeds="2" description="Suite">
<rate rateId="934" />
 <adult rateId="934" id="3" additionalBeds="1" />
 <adult rateId="934" id="4" additionalBeds="2" />
 <child rateId="934" id="0" from="2" to="7" additionalBeds="1" />
</room>
The above message means that Roomcloud will be able to send :
1. daily prices amounts for the first and second extradult supplements for the roomtype
Suite on the rateplan 934
2. daily prices amounts for the extrachild supplement for the roomtype Suite on the rateplan
934
If your system is based on the “per occupancy price” model you must return for every roomtype
and rateplan all the accepted occupancies different from the roomtype base occupancy
For instance, the Roomtype Family accepts occupancy 4 (2Adults and 2 children), occupancy 3
(2 Adults 1 Child) and occupancy 2 (2Adults); the response should look like:
<room id="3384" baseOccupancy="4" description="Family">
<rate rateId="934" />
 <occupancy rateId="934" id="2A1C" beds="3" /><!-- 2 adults and 1 child-->
 <occupancy rateId="934" id="2A" beds="2" /><!-- 2 adults-->
</room>
3. Read and Update Inventory
3.1 View
Roomcloud uses this message to read availabilities, rates and restrictions of a
property between 2 dates
Ex:
<Request userName="MyLogin" password="MyPassword">
 <view hotelId="1111" startDate="2012-10-12" endDate="2012-10-12"/>
</Request>
<Response>
 <availability day="2014-10-10" roomId="2091" quantity="5" >
 <rate rateId="7420" currency="USD" price="80.0" minimumStay="1" coa="false" cod="false" />
 <occupancy rateId="7420" id="1" price="70.0" />
 <adult rateId="7420" id="3" price="20.0" />
 <adult rateId="7420" id="4" price="15.0" />
 <child rateId="7420" id="0" from="2" to="7" price="5.0" />
 <child rateId="7420" id="1" from="2" to="7" price="3.0" />
 <rate rateId="7421" currency="USD" price="70.0" minimumStay="1" coa="false" cod="false" />
 <occupancy rateId="7421" id="1" currency="USD" price="50.0" />
 </availability>
</Response>
Request elements
Level Element/attribute Type Mandatory Description
1 view
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 9 / 21
@hotelId String Y
@startDate Date Y Start date in the format YYYY-MMDD
@endDate Date Y End date in the format YYYY-MM-DD
(included)
Response elements
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 10 / 21
Level Element/attribute Type Mandatory Description
1 availability the day which the availability refers to
@day Date Y
@roomId String Y
Roomtype identifier
@quantity integer Y
number of available rooms
2 rate
@rateId String Y
Rateplan Identifier
@price Double Y
Daily price for the base occupancy
@currency String N possible values are EUR, USD etc
@minimumStay Integer N Minimum number of nights that must be booked for
stays that include the restriction date in any part of the
stay date range
@coa boolean N Close on Arrival restriction
Accepted values true/false
true means that arrival is not admitted on this day and
rateplan
@cod boolean N Close on Departure restriction
Accepted values true/false
true means that departure is not admitted on this day
and rateplan
@closed boolean N Close to sell restriction
Accepted values true/false
true means that the room type is closed to sell on this
day and rateplan
2 occupancy Occupancy based prices
@id String Y
Occupancy identifier
@rateId String Y
Rateplan Identifier
@price Double Y
Daily occupancy price
2 adult Extra adult supplements
@id Y
Extra adult identifier
@rateId Y
Rateplan Identifier
@price Double Y
Extra adult supplement price
2 child Extra child supplements
@id String Y
Extra child identifier
@rateId String Y
Rateplan Identifier
@from Integer Y
starting age of child
@to Integer Y
ending age of child
@price Double Y
Extra child supplement price
3.2 Modify
Roomcloud uses this message to send availabilities for a property between 2 dates
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 11 / 21
Ex:
<Request userName="MyLogin" password="MyPassword">
 <modify hotelId="1111" startDate="2019-10-12" endDate="2019-10-15">
 <availability day="2019-10-12" roomId="1" quantity="5">
 <rate rateId="2782" currency="EUR" price="100.0" minimumStay="1" closed="true" />
 <occupancy rateId="7420" id="1" price="70.0" />
 <adult rateId="7420" id="3"price="20.0" />
 <child rateId="7420" id="1" from="2" to="7" price="3.0" />
 <rate rateId="7421" currency="USD" price="170.0" minimumStay="1" coa="false" cod="false" />
 <occupancy rateId="7421" id="1" price="50.0" />
 </availability>
 <availability day="2019-10-13" roomId="1" quantity="6">
 <rate rateId="2782" price="50.0" minimumStay="1" closed="true" />
 </availability>
 <availability day="2019-10-15" roomId="1" >
 <rate rateId="7421" price="160.0" />
 </availability>
 </modify>
</Request>
Request elements
Level Element/attribute Type Mandatory Description
1 modify
@hotelId String Y
@startDate Date Y Start date in the format YYYY-MMDD
@endDate Date Y End date in the format YYYY-MM-DD
(included)
2 availability the day which the availability refers to
@day Date Y
@roomId String Y
Roomtype identifier
@quantity integer N number of available rooms
3 rate
@rateId String Y
Rateplan Identifier
@price Double N Daily price for the base occupancy
@currency String N possible values are EUR, USD etc
@minimumStay Integer N Minimum number of nights that must be booked for
stays that include the restriction date in any part of the
stay date range
@coa boolean N Close on Arrival restriction
Accepted values true/false
true means that arrival is not admitted on this day and
rateplan
@cod boolean N Close on Departure restriction
Accepted values true/false
true means that departure is not admitted on this day
and rateplan
@closed boolean N Close to sell restriction
Accepted values true/false
true means that the room type is closed to sell on this
day and rateplan
3 occupancy Occupancy based prices
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 12 / 21
@id String Y
Occupancy identifier
@rateId String Y
Rateplan Identifier
@price Double Y
Daily occupancy price
3 adult Extra adult supplements
@id Y
Extra adult identifier
@rateId Y
Rateplan Identifier
@price Double Y
Extra adult supplement price
3 child N Extra child supplements
@id String Y
Extra child identifier
@rateId String Y
Rateplan Identifier
@from Integer Y
starting age of child
@to Integer Y
ending age of child
@price Double Y
Extra child supplement price
Response elements
Level Element/attribute Type Mandatory Description
1 ok To be returned only if the update is
successful
1 error Y To be returned only if the update is not
successful
@message String Y Error description
@code String N
Mandatory availability attributes are day and roomId. Attribute quantity as
well as the subelements are optional.
As a consequence an update request can contain partial data. In case of partial
update, all omitted values should be left unchanged.
In the previous example there’s a full update for the 2019-10-12. The other 2
days are partially updated only.
In case of success the response sent back by the channel must contain the
element ok only.
Ex: successful response
<Response>
<ok/>
</Response>
Otherwise the error description should be returned as value of the message
attribute inside the error element. If possible an error code should be passed
as well. The list of admitted error codes is reported in the next chapter.
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 13 / 21
Ex: response with error
<Response>
 <error message="Problem have been encountered in the modification of the inventory" code="2004" />
</Response>
4. Reservations
Roomcloud uses this message to retrieve reservations od a property between
Request elements
Level Element/attribute Type Mandatory Description
1 reservations
@hotelId String Y
@startDate Date N
@endDate Date N
@useDLM boolean N
Different combinations of attributes correspond to different search criteria:
1. To retrieve all the reservations created or modified after the last call:
• useDLM = “true"
• startDate omitted
• endDate omitted
Ex:
<Request userName="MyLogin" password="MyPassword">
<reservations hotelId="3284" useDLM="true"/>
</Request>
Remarks: Roomcloud sends the above message to retrieve
new/modified/cancelled reservations for every property every 15minutes
2. To retrieve all the reservations created or modified between two dates:
• useDLM = “true"
• startDate = YYYY-MM-DD
• endDate= YYYY-MM-DD
Ex:
<Request userName="MyLogin" password="MyPassword">
 <reservations hotelId="3284" useDLM="true" startDate="2012-10-10" endDate="2012-10-12"/>
</Request>
3. To retrieve all the reservations with check-in date between two dates use:
• useDLM = “false" or omit parameter useDLM
• startDate = YYYY-MM-DD
• endDate = YYYY-MM-DD
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 14 / 21
Ex:
<Request userName="MyLogin" password="MyPassword">
 <reservations hotelId="3284" useDLM="false" startDate="2012-10-10" endDate="2012-10-12"/>
</Request>
Returned informations are a list on reservation elements.
Response elements
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 15 / 21
Level Element/attribute Type Mandatory Description
1 reservation To be returned only if the update is
successful
@id String Y Reservation NUMBER/ID
@dlm DateTime N (yyyy-mm-dd HH:MM:ss)
last modification date
@checkin Date Y yyyy-mm-dd
the check-in date
@checkout Date Y yyyy-mm-dd
the check-out date
@creation_date DateTime Y yyyy-mm-dd HH:MM:ss
reservation date
@firstName String N Customer first name
@lastName String Y Customer second snme
@address String N Customer address
@city String N Customer city
@zipcode String N Customer zip code
@country String N Customer country
@telephone String N Customer phone number
@email String N Customer email
@lang String N Customer preferred language
@rooms Integer N the total number of rooms booked
@adults Integer Y the total number of adults
@children Integer N the total number of children
@price Double Y Reservation amount
@prepaid Double N Amount already collected by the OTA
(amount left to pay at the hotel =priceprepaid)
@currency String N Used currency. Use the ISO 4217 Currency
Code List
@paymentType String N code of the type of payment used by the
client (1 = by transfer , 2 = by postal order ,
3 = by paypal, 4 = by credit card)
@notes String N
@offer String N if there is any offer applied here the channel
can report the description.
@status String Y the state of the reservation (see states
table)
@source_of_busine
ss
String N Description of the source of the
reservations.
Ex: source_of_business=”WebSite”
@pin String N promo code
2 ccData N
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 16 / 21
@ccCode String N CVC of the credit card. The channel can use
its own codes.
@ccNumber String Y credit card number
@ccExpireDate String Y expiration date. The formats accepeted are
MM/yy and MM/yyyy.
@ccHolder String Y holder of the credit card
2 room Y
@id String Y code of the reserved room.
@description String N it's the name of the room
@checkin Date N Date (yyyy-mm-dd)(optional) → the checkin date for this room, can be different from
the reservation checkin
@checkout Date N Date (yyyy-mm-dd)(optional) → the checkout date for this room, can be different from
the reservation checkout
@rateId String Y rate plan code applied to this room
@cancellation_polic
y
String N Cancellation policies
@quantity Integer Y it’s the room quantity reserved
@currency String N currency of the price (ISO 4217 Currency
Code List)
@price Double Y it’s the total amount for the reservation of
this room; it includes supplements and
discounts
@adults Integer Y number of adults hosted in this room
@children Integer N number of children hosted in this room.
@commission Double N amount of the commission applied by the
channel for this room
@status String Y the state of the reservation detail (see
states table).
3 dayPrice N
@roomId String Y it's the code of the room
@day Y Date (yyyy-mm-dd)(required) → the day in
which this price is charged
@price Double Y it’s the total price of this room for this day
ex: if the unit price is 80€ and there are 2
reserved rooms price=160€
@rateId String rate plan code applied for this price
3 supplement
@supplementId String N Supplement ID defined in the original
system
Alternatively it is possible to use one of the
predefined IDs reported in the table below
@description String Y description of this service type
@roomsQuantity Integer N it’s the quantity reserved of this room
@price Double Y Total amount of this supplement
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 17 / 21
Ex: if the unit price is 5 and booked
supplements are 3 then price=15
@number Integer N quantity of this service bought by the client
(=0 if it can be quantified)
@type Integer Y type of this supplement (0=daily, 1=by
person, 2= by number, 3 = by person and
night)
@adults Integer N the total number of adults
3 offer
@offerId String Y promotion ID defined in the original system
@amount_after_ta
x
Double N discount amount (negative number)
amount_after_tax is mandatory if all
amounts in the other elements are including
taxes
@amount_before_t
ax
Double N discount amount before taxes (negative
number)
amount_before_tax is mandatory if all
amounts in the other elements don't include
taxes
@description String Y
3 guest
@firstName String N Customer first name
@lastName String Y Customer second name
@email String N Customer email
@phone String N Customer phone number
@address String N Customer address
@zipCode String N Customer zip code
@city String N Customer city
@country String N Customer country
3 childAge
@age Integer Y age of the nth child in the room
Status table
status Description
2 SUBMITTED
4 CONFIRMED
5 REJECTED
6 NO SHOW
7 DELETED/CANCELLED
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 18 / 21
8 MODIFIED
Predefined Supplement IDs
ID Description
ota_bb Breakfast
ota_hb Half Board
ota_fb Full Board
ota_ai All Inclusive
ota_welcome_kit Welcome Kit
ota_cot Cot
ota_children_kit Children Kit
ota_extrabed Extra bed
ota_parking Car Park
ota_shuttle Shuttle from/to Airport/Train station
ota_spa SPA access
ota_swimming_pool Swimming Pool
ota_internet Internet Access
ota_cleaning_fees Cleening Fees
ota_services_fee Service Fees
ota_pet_fee Pet Fees
ota_air_conditioning Air Conditioning
ota_linen Linen Fees
ota_other Other Fees
ota_short_stay_surcharge Short Stay Surcharge
ota_energy Energy
ota_towels Towels
ota_electricity Electricity
ota_washing_machine Washin Machine
ota_water Water
ota_firewood Firewood
ota_facility Other Facilities
ota_other other Extras
Ex:
<Request userName=”myUsername” password=”myPassword” apikey=”myapikey”>
<reservations hotelId=”11111” useDLM=”true” />
</Request>
<?xml version="1.0" encoding="UTF-8"?>
<Response>
 <reservation id="196325" checkin="2018-03-01" checkout="2018-03-03" firstName="Leopoldo" lastName="Sergi"
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 19 / 21
rooms="2" adults="5" children="0" city="Milano" country="it" email="xmlhelp@tecnes.com"
telephone="3921231234" address="Via GB Piranesi 26" zipCode="20137" price="875.0" prepaid="0.0" status="4"
currency="EUR" paymentType="4" offer="" notes="Late checkin" creation_date="2018-02-13 15:35:57" dlm="2018-
02-13 15:35:57" source_of_business=”WebSite”>
 <ccData ccCode="123" ccNumber="4929xxxxxxxx7659" ccExpireDate="04/2018" ccHolder="Leopoldo Sergi"
/>
 <room id="14738" description="Superior Double - Bed and breakfast" checkin="2018-03-01"
checkout="2018-03-03" rateId="6717" quantity="1" currency="EUR" price="520.0" adults="2" children="0"
childrenPrice="0.0" extraAdults="1" extraAdultsPrice="120.0" commission="0.0" status="4" >
 <supplement roomId="14738" description="&lt;p&gt;Shuttle&lt;/p&gt;" roomsQuantity="1" price="60.0"
type="1" number="0" persons="3" />
 <supplement roomId="14738" description="&lt;p&gt;City Tax&lt;/p&gt;" supplementId="3584"
roomsQuantity="1" price="6.0" type="0" number="1" persons="3" />
 <dayPrice day="2018-03-01" roomId="14738" rateId="6717" price="200.0" extraAdultPrice="60.0"
childrenPrice="0.0" />
 <dayPrice day="2018-03-02" roomId="14738" rateId="6717" price="200.0" extraAdultPrice="60.0"
childrenPrice="0.0" />
 </room>
 <room id="11721" description="Classic Double - Bed and breakfast" checkin="2018-03-01" checkout="2018-
03-03" rateId="6717" quantity="1" currency="EUR" price="235.0" adults="2" children="2" childrenPrice="0.0"
extraAdults="0" extraAdultsPrice="0.0" commission="0.0" status="4" >
 <supplement supplementId="6578" description="&lt;p&gt;Flowers&lt;/p&gt;" roomsQuantity="1"
price="10.0" type="2" number="1" persons="1" />
 <supplement supplementId="ota_shuttle" description="&lt;p&gt;Shuttle&lt;/p&gt;" roomsQuantity="1"
price="40.0" type="1" number="0" persons="2" />
 <supplement supplementId="75849" description="&lt;p&gt;City Tax&lt;/p&gt;" roomsQuantity="1"
price="4.0" type="0" number="1" persons="2" />
 <dayPrice day="2018-03-01" roomId="11721" rateId="6717" price="120.0" extraAdultPrice="0.0"
childrenPrice="0.0" />
 <dayPrice day="2018-03-02" roomId="11721" rateId="6717" price="115.0" extraAdultPrice="0.0"
childrenPrice="0.0" />
<childAge age="3"/>
<childAge age="7"/>
 </room>
 </reservation>
</Response>
<!-- UUID 2ea0537a-2c6f-49c0-ac03-51da968ab072 -->
PUSH METHOD
When a reservation is created, modified or cancelled, the channel can PUSH the reservation to
Roomcloud by sending an HTTP Post request to a dedicated page in the Roomcloud website.
The url to call will contain the reservation id and the hotel id the reservation refers to.
e.g..: https://xml.tecnes.com/hotw/download/channel.jsp?hotelId=xx&reservationId=123456
The body of the post request will contain the XML message including a reservation
<?xml version="1.0" encoding="UTF-8"?>
<Request>
 <reservation …….
 …………………………
 ……………………….
 </reservation>
</request>
If a channel implements the PUSH method, Roomcloud will reduce the frequency of PULL
requests to one every 2 hours.
5. Error Handling
5.1 Error codes
Error codes are 4 digits integer.
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 20 / 21
Code Description Explanation
2001 Internal Error These are internal system error at channel level. Roomcloud can try ro
resend the message again in a few minutes.
2003 Internal Server Error An incremental retry strategy can be put in place.
2002 Internal Timeout Error
2004 Database error
2010 Malformed XML The message is not compliant with the specifications.
2011 Invalid API Error
2100 Auth denied These are authorization errors. Roomcloud will avoid to retry to send the
message as is since an operator action is required to remove the cause
2101 Wrong Credentials of the error
2103 Insufficient access rights
2202 Hotel not found This error happens whenever one of the mapping codes used in the
message (room type id, rat plan id etc) does not belong to the account.
An operator action is required to remove the cause of the error Message
example: You either specified an invalid hotelID or
your account is not linked to this hotel 2203 Mapping Error
6. Roomcloud Inventory
6.1 Roomcloud Data Structure
Every property in RoomCloud can define a set of roomtypes and rateplans and every roomtype
can be associated to one or more rateplan
Cancellation policies are defined at rateplan level
Availability is defined at roomtype level
6.2 The Roomcloud Inventory
The Roomcloud Inventory is the calendar of room availabilities and rates stored
on the RoomCloud database.
Fig 2: inventory
Availability is defined at roomtype level
For each roomtype/rataplan and each day it is possible to define:
1. the price for the roomtype standard capacity
2. the closure to sell
3. the minimum stay restrinction
4. the closure on arrival
5. the closure on departure
6. the price for the every occupancy lower then the roomtype standard
capacity
7. the prices for the adults that can be hosted in the additional beds
8. the prices for the children that can be hosted in the additional beds
RoomCloud XML OTA API
Ver. 3.8 05/04/2024
Page 21 / 21
Each RoomCloud roomtype/rateplan can be mapped to one or more “room type
/ rate plan" on different channels.
Remark: every time that a Roomcloud user updates the calendar, Roomcloud simultanously
pushes the updated values to all the connected channels
7. Testing
7.1 Test Page
In order to test the XML messages exchange you can use our test page:
https://apitest.roomcloud.net/be/ota/testOtaApi.jsp
Put the service_url in the “url" field.
Put the message you want to try in the “xml request" text area and click on
Send XML.
The response returned by your server will be displayed in the “xml response"
text area.
7.2 Roomcloud Test Account
Once you're ready, we can go on with the channel configuration in Roomcloud
For this purpose we will need from you
1.the credentials of a test account defined on your system
2.the service_url, i.e. the endpoint API url of your system
Once we have these informations we will proceed with the mapping on our side and we'll give
you a test account in Roomcloud that will let you do the final tests.
7.3 Contacts
Email to: xmlhelp@tecnes.com