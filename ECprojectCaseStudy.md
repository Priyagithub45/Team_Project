# E-Commerce Case Study

## Overview
A popular suburb of Cleckhuddersfax is distinguished by the fact that it continues to have a vibrant local shopping area that is dominated by small independent businesses.
Being part of a larger city there are ample opportunities for locals to drive a short distance to find a large supermarket - yet the small shops continue to thrive.
However, national chains have started to find space in the area by setting up convenience stores and now there is a proposal to develop a larger store.
The traders are aware that they cannot compete on opening hours without losing out on family life, and that there are more locals that would shop with them if they weren’t working most of the time when the shops were open.
The stores selling fresh goods have decided to band together and explore the possibility of offering a joint e-commerce portal to their shops.
They have recruited someone to explore issues of food safety, collection etc.  and they think they may have a proposal that will work.
They now want to fund the development of a prototype e-commerce platform.
You are required to develop a prototype web based system to address their requirements.

## Initial Scope
An initial scoping meeting has been held with the traders leading to an initial list of features.
The notes at this point follow the conversation and need to be distilled into a more formal requirements listing.
There will be an opportunity to seek clarification of these requirements.

## Customer Interface
* There will be 5 traders in the initial pilot project: butchers, greengrocer, fishmonger, bakery and delicatessen.
* Users should be able to navigate to products by shop or by product type.
* For the pilot all traders have agreed that their product lines should not overlap and that their products will be unique to them.
* They are not expecting the portal to grow significantly over time, as such the system should support a maximum of 10 shops in the first instance.
* As part of the development process they would like to see some alternative designs.
* Their inclination is towards a look that encapsulates the heritage of the area, but they are open to new and creative ideas.
* They expect the site to be viewable on mobile as well as desktop devices and viewable on the most popular browsers.
* Users should be provided with a single basket that holds goods that spans all traders and requires a single payment.
* The basket and totals reported to the traders should include a breakdown of goods ordered and payment received by trader.
* The collection scheme they have devised will be responsible for physically putting orders together.
* Users should be required to register before they can place an order and be logged in to access the basket.
* Users should be able to review and update their account details.
* Ideally registration and account updates should be confirmed through interaction with emails.
* They have identified a small area of one of the shops that will act as the collection point (there will be no deliveries for the pilot).
* They will offer 3 collection slots 10-13, 13-16, 16-19.  For the pilot these will only be for Wed, Thus, Fri.
* When placing and paying for their order users should identify a collection slot, which must be at least 24 hours after placing the order.
* There will be a maximum of 20 orders per slot.
* They have not decided on a payment scheme, but feel they should offer Paypal.
* In addition the Stripe payment gateway has been suggested to them and they would be interested in seeing this explored.

## Trader Interface
* Traders should be able to manage their products and information on their shops via a web interface.
* They should be able to add/delete/update.
* All products will minimally have a unique ID, be associated with a trader, have a short name, a description, an item price, quantity per item, stock available, min order, max order, allergy information.
* Traders will login to the database on a daily basis to view reports on orders and stock levels etc.
* Traders should have a trader login that allows them access to their details only.
* They should be able to view and update the details of their trader account.
* There should be an admin login that can access any of the trader accounts.

## Management Interface
* A management dashboard is required.
* The dashboard will give the traders access to a range of daily and periodic reports.
* Traders will login each morning and run a report on the orders placed for their goods.
* The report will identify the goods and quantities that have been ordered.
* Reports should be presented so that it is clear which delivery slots goods are required for and they will use this information to label goods for collection with a customer ID and order ID as well as product details.
* Traders will be provided with a weekly finance report which will identify payments to be made to them resulting from the previous 7 days orders.
* The report will only cover orders that have been delivered.
* Traders will be provided with a monthly report on their product sales.
* They will be able to run this report ordered in a number of ways: alphabetically, by total number of orders per product, by total income per product.
