=== Cadastro Certo – CPF e CNPJ Alfanumérico for WooCommerce ===
Contributors: deltatech
Tags: cpf, cnpj, brazil, checkout blocks, checkout fields
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CPF, alphanumeric CNPJ, state registration, number and neighborhood for the classic and block checkouts. Keeps Brazilian Market data.

== Description ==

Cadastro Certo adds the fields a Brazilian store needs to WooCommerce: person type, CPF, CNPJ, company name, state registration (inscrição estadual), street number and neighborhood (bairro). It works in the classic checkout and in the block checkout, which is the default for new stores.

**Alphanumeric CNPJ.** Since July 2026 the Brazilian Federal Revenue (Receita Federal) issues CNPJ numbers with letters, such as 12.ABC.345/01DE-35. Validators that only accept digits reject these customers. This plugin computes the check digits with the official rule (character value = ASCII code minus 48, modulo 11), and digit-only CNPJ numbers keep working the same way.

**Block checkout.** The fields are registered with WooCommerce's own additional checkout fields API. CPF is shown to individuals; CNPJ, company name and state registration to companies. The server validates everything again before the order is created.

**Same data as Brazilian Market on WooCommerce.** Everything is saved under the meta keys that the "Brazilian Market on WooCommerce" plugin used: `_billing_cpf`, `_billing_cnpj`, `_billing_persontype`, `_billing_ie`, `_billing_number`, `_billing_neighborhood`, `_billing_cellphone`, `_shipping_number` and `_shipping_neighborhood` on the order, and the same keys without the leading underscore on the customer. This also applies to orders placed through the block checkout, so payment gateways, invoicing tools and ERPs that already read these keys keep working.

= Features =

* Person type: individual (CPF), company (CNPJ) or both, chosen by the customer.
* CPF and CNPJ (digits only and alphanumeric) validated by check digit.
* Company name and state registration (off, optional or required; accepts ISENTO, meaning exempt).
* Street number and neighborhood in the billing and shipping addresses.
* Cell phone, birth date and gender, if you turn them on (off by default).
* Input masks for CPF, CNPJ (with letters), cell phone and date.
* Option to skip document fields for customers outside Brazil.
* Brazilian address format (street, number, complement, neighborhood) in emails, on the order screen and in My Account.
* Editable fields on the admin order screen and on the customer profile.
* The same fields in the REST API for orders and customers (`billing.cpf`, `billing.cnpj`, `billing.number`…), with the names and formats the old plugin used.
* Compatible with High-Performance Order Storage (HPOS).

No feature expires or is locked. The plugin does not connect to any external service and does not collect data.

= Add-on for wholesale and B2B stores =

If your store sells to companies, there is a separately sold add-on with address and company lookup by CNPJ and postal code, state registration validation for each state, and blocking of inactive CNPJ numbers. More information at [deltatech.network/plugins](https://www.deltatech.network/plugins/). This plugin works fully without it.

= Known limits =

* In the block checkout, document fields are placed in the "Contact information" section. That is where the WooCommerce API places fields that are not repeated for the shipping address. Street number and neighborhood are part of the address form, after the standard address fields (the API does not allow placing them between core fields).
* In the block checkout, the saved-address summary card shows street, complement, city and postal code but not number and neighborhood: WooCommerce builds that card in the browser and only knows its own fields. Number and neighborhood are visible when the customer clicks "Edit", and they appear in the order, emails and admin.
* In the block checkout, the person type field starts empty. Until the customer picks one, both CPF and CNPJ are shown; after that, only the matching one.
* In My Account, documents are in "Account details" and number and neighborhood in "Addresses", because that is where WooCommerce shows block checkout fields.
* Showing fields by person type in the block checkout requires WooCommerce 9.9 or newer.
* While Brazilian Market on WooCommerce is active, Cadastro Certo stays on hold (see Installation). During that time the block checkout has no Brazilian fields, because the old plugin does not support it.

== Installation ==

1. Install and activate the plugin from Plugins > Add New (or upload the .zip file).
2. Go to WooCommerce > Settings > CPF e CNPJ and choose which fields to ask for.
3. The fields now appear in the classic checkout and in the block checkout.

If Brazilian Market on WooCommerce is active, Cadastro Certo stays on hold so fields are not duplicated, and shows a notice on the Plugins screen with a button to take over. Taking over copies the old plugin's settings and deactivates it; no data is deleted.

== Frequently Asked Questions ==

= Vim do Brazilian Market on WooCommerce: perco meus dados? (I am coming from Brazilian Market on WooCommerce: will I lose my data?) =

No. Cadastro Certo reads and writes the same meta keys (`_billing_cpf`, `_billing_cnpj`, `_billing_number`, `_billing_neighborhood` and the others). Existing orders and customer profiles keep showing CPF, CNPJ, number and neighborhood, and the old plugin's settings are copied on activation. Uninstalling Cadastro Certo does not delete this data either.

= Does it accept CNPJ numbers with letters? =

Yes. The calculation follows the Serpro/Receita Federal document on check digits for the alphanumeric CNPJ. Lowercase letters are converted to uppercase.

= Does it work with the block checkout? =

Yes. The fields are registered through WooCommerce's official additional checkout fields API, with server-side validation.

= Do the input masks work in the block checkout? =

The WooCommerce API has no input masks. The plugin formats CPF, CNPJ and cell phone while the customer types, and the server stores the formatted value in any case.

= My payment gateway or ERP reads the CPF from the order. Will it keep working? =

If it reads `_billing_cpf`, `_billing_cnpj` or the `billing.cpf` and `billing.cnpj` fields from the REST API, yes: these are the same keys and fields as in the old plugin, also filled for block checkout orders. Check whether your system accepts CNPJ numbers with letters; that depends on it.

= Does the plugin validate the state registration? =

It checks the format (digits, with or without dots, or ISENTO). The rules for each state are part of the wholesale and B2B add-on.

= Does it send data anywhere? =

No. All validation runs on your server and in the customer's browser.

== Screenshots ==

1. Block checkout: company customer with an alphanumeric CNPJ.
2. Classic checkout: company customer with an alphanumeric CNPJ typed in lowercase, formatted by the mask.
3. Block checkout: an invalid CNPJ is rejected.
4. Admin order screen with person type, CNPJ, state registration, number and neighborhood.
5. Settings in WooCommerce > Settings > CPF e CNPJ.
6. Notice shown while Brazilian Market on WooCommerce is still active, with the take-over button.

== Changelog ==

= 1.0.0 =
* First release: person type, CPF, digit-only and alphanumeric CNPJ, company name, state registration, number, neighborhood, cell phone, birth date and gender; classic and block checkouts; same meta keys as Brazilian Market on WooCommerce; REST API fields; HPOS.

== Upgrade Notice ==

= 1.0.0 =
First release.
