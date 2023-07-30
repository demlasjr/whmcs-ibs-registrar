<?php

/***********************************************************
IMPORTANT IF YOU INTEND TO MAKE CHANGES IN THIS FILE
The entries in this file are like this (taking as example one option for .fr domains but all others are similar):
$additionaldomainfields[".fr"][0] = [
    "Name" => "Holder Type",
    "DisplayName" => "Type titulaire",
    "Type" => "dropdown",
    "Options" => "individual|Personne Physique,company|Entreprise,trademark|Titulaire de Marque,association|Association,other|Autre",
    "Default" => "individual",
    ];

In the above do not make any changes in the "Name" field as it will make the module to stop workling properly
The text that will appear in the web interface is on the right of "DisplayName" =>
For example you can change this:
"DisplayName" => "Type titulaire",
with this:
"DisplayName" => "Owner type",

Also for entries that have drop downs the entries are a comma separated list of values liek this:
"Options" => "individual|Personne Physique,company|Entreprise,trademark|Titulaire de Marque,association|Association,other|Autre",
To generalize this the entries are a comma separated list of  "key|value" entries.  In that never change the key part (what is on the left of the | character. For example you can change this:
"Options" => "individual|Personne Physique,company|Entreprise,trademark|Titulaire de Marque,association|Association,other|Autre",
to this:
"Options" => "individual|Individual,company|Company,trademark|Trademark owner,association|Association,other|Other",

As you can see for each key|value group only the value can be changed. Changing the key will cause malfunction of the module

NOTE: Translation only works with WHMCS 4.5 and above!

 ************************************************************/

/*
  If you intend to register .uk domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */

/* * ************* START .AU ****************** */
$additionaldomainfields[".com.au"] = [];
$additionaldomainfields[".com.au"][] = [
    "Name" => "comaulegalentitytype",
    "DisplayName" => "Legal Entity Type",
    "Type" => "dropdown",
    "Options" => implode(",", [
        "ABN|Australian Business Number",
        "ACN|Australian Company Number",
        "TM|Trademark",
        "OTHER|Other"
    ]),
    "Required" => true,
    "LangVar" => "comauLegalEntityType",
];
$additionaldomainfields[".com.au"][] = [
    "Name" => "comaurelationtype",
    "DisplayName" => "Relation type",
    "Type" => "dropdown",
    "Options" => implode(",", [
        "Company|Company",
        "RegisteredBusiness|Registered Business",
        "SoleTrader|Sole Trader",
        "Partnership|Partnership",
        "TrademarkOwner|Trademark Owner",
        "PendingTMOwner|Pending Trademark Owner",
        "CitizenResident|Citizen / Resident",
        "IncorporatedAssociation|Incorporated Association",
        "Club|Club",
        "NonProfitOrganisation|Non-Profit Organisation",
        "Charity|Charity",
        "TradeUnion|Trade Union",
        "IndustryBody|Industry Body",
        "CommercialStatutoryBody|Commercial Statutory Body",
        "PoliticalParty|Political Party",
        "Other|Other"
    ]),
    "Required" => true,
    "LangVar" => "comauRelationType"
];
$additionaldomainfields[".com.au"][] = [
    "Name" => "comaucompanynumber",
    "DisplayName" => "Company / Trademark ID",
    "Type" => "text",
    "Size" => "50",
    "Required" => true,
    "LangVar" => "comauCompanyNumber",
];
$additionaldomainfields[".com.au"][] = [
    "Name" => "comautrademarkownername",
    "DisplayName" => "Trademark Owner Name",
    "Type" => "text",
    "Size" => "120",
    "Required" => true,
    "LangVar" => "comauTrademarkOwnerName"
];

/* * ************* START .CA ****************** */
$additionaldomainfields[".ca"] = [];
$additionaldomainfields[".ca"][] = [
    "Name" => "calegalentitytype",
    "DisplayName" => "Lega Entity Type",
    "Type" => "dropdown",
    "Options" => implode(",", [
        "ABO|Aboriginal Peoples indigenous to Canada",
        "ASS|Canadian Unincorporated Association",
        "CCO|Corporation (Canada or Canadian province or territory)",
        "CCT|Canadian citizen",
        "EDU|Canadian Educational Institution",
        "GOV|Government or government entity in Canada",
        "HOP|Canadian Hospital",
        "INB|Indian Band recognized by the Indian Act of Canada",
        "LAM|Canadian Library, Archive or Museum",
        "LGR|Legal Rep. of a Canadian Citizen or Permanent Resident",
        "MAJ|Her Majesty the Queen",
        "OMK|Official mark registered in Canada",
        "PLT|Canadian Political Party",
        "PRT|Partnership Registered in Canada",
        "RES|Permanent Resident of Canada",
        "TDM|Trade-mark registered in Canada",
        "TRD|Canadian Trade Union",
        "TRS|Trust established in Canada"
    ]),
    "Required" => true,
    "LangVar" => "caLegalEntityType",
];
$additionaldomainfields[".ca"][] = [
    "Name" => "catrademarknumber",
    "DisplayName" => "Trademark Number",
    "Type" => "text",
    "Size" => "50",
    "Required" => [
        "calegalentitytype" => [
            "TDM"
        ]
    ],
    "LangVar" => "caTrademark",
];
$additionaldomainfields[".ca"][] = [
    "Name" => "catrademark",
    "DisplayName" => "Domain Name is a Trademark",
    "Type" => "dropdown",
    "Options" => implode(",", [
        "0|No",
        "1|Yes"
    ]),
    "Required" => false,
    "LangVar" => "caTrademark",
];

/* * *************  END .CA  ****************** */

/* * ************* START .UK ****************** */
$additionaldomainfields[".co.uk"] = [];
$additionaldomainfields[".co.uk"]["legaltype"] = [
    "Name" => "Legal Type",
    "DisplayName" => "Legal Type",
    "Type" => "dropdown",
    "Options" => "IND|Individual,LTD|UK Limited Company,PLC|UK Public Limited Company,PTNR|UK Partnership,LLP|UK Limited Liability Partnership,STRA|Sole Trader,IP|Industrial/Provident Registered Company,SCH|UK School,RCHAR|UK Registered Charity,GOV|Government Body, CRC|Corporation By Royal Charter,STAT|Uk Statutory Body,OTHER|UK Entity (other),FIND|Non-UK Individual,FCORP|Non-Uk Corporation,FOTHER|Other foreign entity",
    "Default" => "Individual",
    "LangVar" => "ukLegalType",
];
# the following is NOT required when "Legal Type" is "Individual"
$additionaldomainfields[".co.uk"]["registrationnumber"] = [
    "Name" => "Company ID Number",
    "DisplayName" => "Company Registration Number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "ukRegistrationNumber",
];
# the following is required only when "Legal Type" is "Individual"
$additionaldomainfields[".co.uk"]["hidewhois"] = [
    "Name" => "WHOIS Opt-out",
    "DisplayName" => "Hide whois details",
    "Type" => "tickbox",
    "LangVar" => "ukhidewhois",
];

$additionaldomainfields[".org.uk"] = $additionaldomainfields[".co.uk"];

$additionaldomainfields[".me.uk"] = $additionaldomainfields[".co.uk"];
$additionaldomainfields[".uk"] = $additionaldomainfields[".co.uk"];

/* * ************* END .UK ****************** */

/*
  If you intend to register .eu domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */
/* * ************* START .EU ****************** */
$additionaldomainfields[".eu"] = [];
$additionaldomainfields[".eu"][] = [
    "Name" => "Entity Type",
    "Remove" => true,
];
$additionaldomainfields[".eu"][] = [
    "Name" => "EU Country of Citizenship",
    "Remove" => true
];
$additionaldomainfields[".eu"][] = [
    "Name" => "Language",
    "DisplayName" => "Language",
    "Type" => "dropdown",
    "Options" => "cs|Czech,da|Danish,de|German,el|Greek,en|English,es|Spanish,et|Estonian,fi|Finnish,fr|French,hu|Hungarian,it|Italian,lt|Lithuanian,lv|Latvian,mt|Maltese,nl|Nederlands,pl|Polish,pt|Portuguese,sk|Slovak,sl|Slovenian,sv|Swedish,ro|Romanian,bg|Bulgarian,ga|Irish",
    "Default" => "en",
    "Required" => true,
];
/* * ************* END .EU ****************** */

/* * ************* START .BE ****************** */
$additionaldomainfields[".be"] = [];
$additionaldomainfields[".be"][] = [
    "Name" => "Language",
    "DisplayName" => "Language",
    "Type" => "dropdown",
    "Options" => "en|English,fr|French,nl|Nederlands",
    "Default" => "en",
    "Required" => true,
];
/* * ************* END .BE ****************** */

/*
  If you intend to register .asia domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */

/* * ************* START .ASIA ****************** */
$additionaldomainfields[".asia"] = [];
$additionaldomainfields[".asia"]["locality"] = [
    "Name" => "Locality",
    "DisplayName" => "Locality",
    "Type" => "dropdown",
    /* "Options" => "AQ|Antarctica,AM|Armenia,AU|Australia,AZ|Azerbaijan,BH|Bahrain,BD|Bangladesh,BT|Bhutan,BN|Brunei,KH|Cambodia,CN|China,CX|Christmas Island,CC|Cocos (Keeling) Islands,CK|Cook Islands,CY|Cyprus,TL|East Timor,FM|Federated States of Micronesia,FJ|Fiji,GE|Georgia,HM|Heard Island and Mcdonald Islands,HK|Hong Kong,IN|India,ID|Indonesia,IR|Iran,IQ|Iraq,IL|Israel,JP|Japan,JO|Jordan,KZ|Kazakhstan,KI|Kiribati,KW|Kuwait,KG|Kyrgyzstan,LA|Laos,LB|Lebanon,MO|Macau,MY|Malaysia,MV|Maldives,MH|Marshall Islands,MN|Mongolia,MM|Myanmar,NR|Nauru,NP|Nepal,NZ|New Zealand,NU|Niue,NF|Norfolk Island,KP|North Korea,OM|Oman,PK|Pakistan,PW|Palau,PS|Palestinian Occupied Territories,PG|Papua New Guinea,PH|Philippines,QA|Qatar,WS|Samoa,SA|Saudi Arabia,SG|Singapore,SB|Solomon Islands,KR|South Korea,LK|Sri Lanka,SY|Syria,TW|Taiwan,TJ|Tajikistan,TH|Thailand,TK|Tokelau,TO|Tonga,TR|Turkey
TM|Turkmenistan,TV|Tuvalu,AE|United Arab Emirates,UZ|Uzbekistan,VU|Vanuatu,VN|Vietnam,YE|Yemen",*/
    "Options" => "AF|Afghanistan,AQ|Antarctica,AM|Armenia,AU|Australia,AZ|Azerbaijan,BH|Bahrain,BD|Bangladesh,BT|Bhutan,BN|Brunei,KH|Cambodia,CN|China,CX|Christmas Island,CC|Cocos (Keeling) Islands,CK|Cook Islands,CY|Cyprus,TL|East Timor,FM|Federated States of Micronesia,FJ|Fiji,GE|Georgia,HM|Heard Island and Mcdonald Islands,HK|Hong Kong,IN|India,ID|Indonesia,IQ|Iraq,IL|Israel,JP|Japan,JO|Jordan,KZ|Kazakhstan,KI|Kiribati,KW|Kuwait,KG|Kyrgyzstan,LA|Laos,LB|Lebanon,MO|Macau,MY|Malaysia,MV|Maldives,MH|Marshall Islands,MN|Mongolia,NR|Nauru,NP|Nepal,NZ|New Zealand,NU|Niue,NF|Norfolk Island,OM|Oman,PK|Pakistan,PW|Palau,PS|Palestinian Occupied Territories,PG|Papua New Guinea,PH|Philippines,QA|Qatar,WS|Samoa,SA|Saudi Arabia,SG|Singapore,SB|Solomon Islands,KR|South Korea,LK|Sri Lanka,TW|Taiwan,TJ|Tajikistan,TH|Thailand,TK|Tokelau,TO|Tonga,TR|Turkey,TM|Turkmenistan,TV|Tuvalu,AE|United Arab Emirates,UZ|Uzbekistan,VU|Vanuatu,VN|Vietnam,YE|Yemen",
    /*"Default" => "AQ",*/
    "Default" => "AF",
    "Required" => true,
    "LangVar" => "asiaLocality",
];
$additionaldomainfields[".asia"]["legalentity"] = [
    "Name" => "Legal Entity Type",
    "DisplayName" => "Legal Entity Type",
    "Type" => "dropdown",
    "Options" => "naturalPerson|Natural person,corporation|Corporation,cooperative|Cooperative,partnership|Partnership,government|Government,politicalParty|Political Party,society|Society,institution|Institution,other|Other",
    "Default" => "naturalPerson",
    "LangVar" => "asiaLegalEntity",
];
$additionaldomainfields[".asia"]["identificationform"] = [
    "Name" => "Identification Form",
    "DisplayName" => "Identification Form",
    "Type" => "dropdown",
    "Options" => "passport|Passport,certificate|Certificate,legislation|Legislation,societyRegistry|Society Registry,politicalPartyRegistry|Political Party Registry,other|Other",
    "Default" => "passport",
    "LangVar" => "asiaIdentificationForm"
];
$additionaldomainfields[".asia"]["identificationnumber"] = [
    "Name" => "Identification Number",
    "DisplayName" => "Identification Number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "LangVar" => "asiaIdentificationNumber"
];
# the following is required only when "Legal Entity Type" is "other"
$additionaldomainfields[".asia"]["otherlegalentity"] = [
    "Name" => "Other legal entity type",
    "DisplayName" => "Other legal entity type",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "Langvar" => "asiaOtherLegalEntity"
];
# the following is required only when "Identification Form" is "other"
$additionaldomainfields[".asia"]["otheridentificationform"] = [
    "Name" => "Other identification form",
    "DisplayName" => "Other identification form",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "asiaOtherIdentificationForm",
];
/* * ************* END .ASIA ****************** */

/*
  If you intend to register .fr domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */

/* * ************* START .FR****************** */
$additionaldomainfields[".fr"] = [];
$additionaldomainfields[".fr"]["holdertype"] = [
    "Name" => "Holder Type",
    "DisplayName" => "Holder Type",
    "Type" => "dropdown",
    "Options" => "individual|Individual,company|Company,trademark|Trademark owner,association|Association,other|Other",
    "Default" => "individual",
    "LangVar" => "frHolderType",
];

# the following fields are required when "Holder Type" is "individual"
$additionaldomainfields[".fr"]["birthdate"] = [
    "Name" => "Birth Date YYYY-MM-DD",
    "DisplayName" => "Birth Date (YYYY-MM-DD)",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frBirthDate"
];
$additionaldomainfields[".fr"]["birthcountry"] = [
    "Name" => "Birth Country Code",
    "DisplayName" => "Birth Country",
    "Options" => "AF|Afghanistan,AX|Aland Islands,AL|Albania,DZ|Algeria,AS|American Samoa,AD|Andorra,AO|Angola,AI|Anguilla,AQ|Antarctica,AG|Antigua and Barbuda,AR|Argentina,AM|Armenia,AW|Aruba,AU|Australia,AT|Austria,AZ|Azerbaijan,BS|Bahamas,BH|Bahrain,BD|Bangladesh,BB|Barbados,BY|Belarus,BE|Belgium,BZ|Belize,BJ|Benin,BM|Bermuda,BT|Bhutan,BO|Bolivia,BA|Bosnia and Herzegovina,BW|Botswana,BV|Bouvet Island,BR|Brazil,IO|British Indian Ocean Territory,VG|British Virgin Islands,BN|Brunei,BG|Bulgaria,BF|Burkina Faso,BI|Burundi,KH|Cambodia,CM|Cameroon,CA|Canada,CV|Cape Verde,KY|Cayman Islands,CF|Central African Republic,TD|Chad,CL|Chile,CN|China,CX|Christmas Island,CC|Cocos (Keeling) Islands,CO|Colombia,KM|Comoros,CG|Congo,CK|Cook Islands,CR|Costa Rica,HR|Croatia,CU|Cuba,CY|Cyprus,CZ|Czech Republic,CD|Democratic Republic of Congo,DK|Denmark,DJ|Djibouti,DM|Dominica,DO|Dominican Republic,TL|East Timor,EC|Ecuador,EG|Egypt,SV|El Salvador,GQ|Equatorial Guinea,ER|Eritrea,EE|Estonia,ET|Ethiopia,FK|Falkland Islands,FO|Faroe Islands,FM|Federated States of Micronesia,FJ|Fiji,FI|Finland,FR|France,GF|French Guyana,PF|French Polynesia,TF|French Southern Territories,GA|Gabon,GM|Gambia,GE|Georgia,DE|Germany,GH|Ghana,GI|Gibraltar,GR|Greece,GL|Greenland,GD|Grenada,GP|Guadeloupe,GU|Guam,GT|Guatemala,GG|Guernsey,GN|Guinea,GW|Guinea-Bissau,GY|Guyana,HT|Haiti,HM|Heard Island and Mcdonald Islands,HN|Honduras,HK|Hong Kong,HU|Hungary,IS|Iceland,IN|India,ID|Indonesia,IR|Iran,IQ|Iraq,IE|Ireland,IM|Isle of man,IL|Israel,IT|Italy,CI|Ivory Coast,JM|Jamaica,JP|Japan,JE|Jersey,JO|Jordan,KZ|Kazakhstan,KE|Kenya,KI|Kiribati,KW|Kuwait,KG|Kyrgyzstan,LA|Laos,LV|Latvia,LB|Lebanon,LS|Lesotho,LR|Liberia,LY|Libya,LI|Liechtenstein,LT|Lithuania,LU|Luxembourg,MO|Macau,MK|Macedonia,MG|Madagascar,MW|Malawi,MY|Malaysia,MV|Maldives,ML|Mali,MT|Malta,MH|Marshall Islands,MQ|Martinique,MR|Mauritania,MU|Mauritius,YT|Mayotte,MX|Mexico,MD|Moldova,MC|Monaco,MN|Mongolia,ME|Montenegro,MS|Montserrat,MA|Morocco,MZ|Mozambique,MM|Myanmar,NA|Namibia,NR|Nauru,NP|Nepal,NL|Netherlands,AN|Netherlands Antilles,NC|New Caledonia,NZ|New Zealand,NI|Nicaragua,NE|Niger,NG|Nigeria,NU|Niue,NF|Norfolk Island,KP|North Korea,MP|Northern Mariana Islands,NO|Norway,OM|Oman,PK|Pakistan,PW|Palau,PS|Palestinian Occupied Territories,PA|Panama,PG|Papua New Guinea,PY|Paraguay,PE|Peru,PH|Philippines,PN|Pitcairn Islands,PL|Poland,PT|Portugal,PR|Puerto Rico,QA|Qatar,RE|Reunion,RO|Romania,RU|Russia,RW|Rwanda,BL|Saint Barthélemy,SH|Saint Helena and Dependencies,KN|Saint Kitts and Nevis,LC|Saint Lucia,MF|Saint Martin,PM|Saint Pierre and Miquelon,VC|Saint Vincent and the Grenadines,WS|Samoa,SM|San Marino,ST|Sao Tome and Principe,SA|Saudi Arabia,SN|Senegal,RS|Serbia,SC|Seychelles,SL|Sierra Leone,SG|Singapore,SK|Slovakia,SI|Slovenia,SB|Solomon Islands,SO|Somalia,ZA|South Africa,GS|South Georgia and South Sandwich Islands,KR|South Korea,ES|Spain,LK|Sri Lanka,SD|Sudan,SR|Suriname,SJ|Svalbard and Jan Mayen,SZ|Swaziland,SE|Sweden,CH|Switzerland,SY|Syria,TW|Taiwan,TJ|Tajikistan,TZ|Tanzania,TH|Thailand,TG|Togo,TK|Tokelau,TO|Tonga,TT|Trinidad and Tobago,TN|Tunisia,TR|Turkey,TM|Turkmenistan,TC|Turks And Caicos Islands,TV|Tuvalu,VI|US Virgin Islands,UG|Uganda,UA|Ukraine,AE|United Arab Emirates,GB|United Kingdom,US|United States,UM|United States Minor Outlying Islands,UY|Uruguay,UZ|Uzbekistan,VU|Vanuatu,VA|Vatican City,VE|Venezuela,VN|Vietnam,WF|Wallis and Futuna,EH|Western Sahara,YE|Yemen,ZM|Zambia,ZW|Zimbabwe",
    "Type" => "dropdown",
    "Default" => "FR",
    "Required" => false,
    "LangVar" => "frBirthCountry",
];
# the following are required only when "Birth Country Code" is "fr"
$additionaldomainfields[".fr"]["birthcity"] = [
    "Name" => "Birth City",
    "DisplayName" => "Birth City",
    "Type" => "text",
    "Size" => "20",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frBirthCity",
];
$additionaldomainfields[".fr"]["birthpostalcode"] = [
    "Name" => "Birth Postal code",
    "DisplayName" => "Birth Postal code",
    "Type" => "text",
    "Size" => "10",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frBirthPostalCode",
];

# the following fields are required when "Holder Type" is "company" or "trademark"
# the field "Name" is also required when "Holder Type" is "company" or "association" or "other"
# the field "Siren" or "Trade Mark" is also required when "Holder Type" is "other"
$additionaldomainfields[".fr"]["siren"] = [
    "Name" => "Siren",
    "DisplayName" => "SIREN",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frSiren",
];
$additionaldomainfields[".fr"]["vat"] = [
    "Name" => "VATNO",
    "DisplayName" => "VAT number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frVAT"
];

$additionaldomainfields[".fr"]["duns"] = [
    "Name" => "DUNSNO",
    "DisplayName" => "DUNS number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frDuns",
];

# the following field is also required when  "Holder Type" is "trademark"
$additionaldomainfields[".fr"]["trademark"] = [
    "Name" => "Trade Mark",
    "DisplayName" => "Trademark",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frTradeMark",
];

# the following fields are also required when "Holder Type" is "association"
$additionaldomainfields[".fr"]["waldec"] = [
    "Name" => "Waldec",
    "DisplayName" => "Waldec",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frWaldec",
];
$additionaldomainfields[".fr"]["dateofassociation"] = [
    "Name" => "Date of Association YYYY-MM-DD",
    "DisplayName" => "Date of Association (YYYY-MM-DD)",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "Langvar" => "frDateOfAssociation"
];
$additionaldomainfields[".fr"]["dateofpublication"] = [
    "Name" => "Date of Publication YYYY-MM-DD",
    "DisplayName" => "Date of Publication (YYYY-MM-DD)",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frDateOfPublication"
];
$additionaldomainfields[".fr"]["announcenumber"] = [
    "Name" => "Announce No",
    "DisplayName" => "Announcement number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frAnnounceNumber",
];
$additionaldomainfields[".fr"]["pageno"] = [
    "Name" => "Page No",
    "DisplayName" => "Page number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frPageNo",
];

# the following fields are also required when "Holder Type" is "other"
$additionaldomainfields[".fr"]["otherlegalstatus"] = [
    "Name" => "Other Legal Status",
    "DisplayName" => "Other legal status",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => false,
    "LangVar" => "frOtherLegalStatus"
];

$additionaldomainfields[".fr"]["restrictedpublication"] = [
    "Name" => "Restricted Publication",
    "DisplayName" => "Hide detais in whois (for individual only)",
    "Type" => "tickbox",
    "LangVar" => "frRestrictedPublication",
];


/* * ************* END .FR ****************** */


/*
  If you intend to register .re domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */

/* * ************* START .RE/.PM/.TF/.WF/.YT ****************** */
// all same as .fr
$additionaldomainfields[".re"] = $additionaldomainfields[".fr"];
$additionaldomainfields[".pm"] = $additionaldomainfields[".fr"];
$additionaldomainfields[".tf"] = $additionaldomainfields[".fr"];
$additionaldomainfields[".wf"] = $additionaldomainfields[".fr"];
$additionaldomainfields[".yt"] = $additionaldomainfields[".fr"];
/* * ************* END .RE/.PM/.TF/.WF/.YT ****************** */

/*
  If you intend to register .it domains using this module make sure that the following exists in your includes/additionaldomainfields.php file,
  if not exists then add the following at the end of the file includes/additionaldomainfields.php
 */

/* * ************* START .IT****************** */
$additionaldomainfields[".it"] = [];
$additionaldomainfields[".it"]["legalentity"] = [
    "Name" => "Legal Entity Type",
    "DisplayName" => "Holder Type",
    "Type" => "dropdown",
    "Options" => implode(",", [
        "1. Italian and foreign natural persons",
        "2. Companies/one man companies",
        "3. Freelance workers/professionals",
        "4. non-profit organizations",
        "5. public organizations",
        "6. other subjects",
        "7. foreigners who match 2 - 6"
    ]),
    "Default" => "1. Italian and foreign natural persons",
    "Required" => true,
    "LangVar" => "itLegalEntity"
];
$additionaldomainfields[".it"]["nationality"] = [
    "Name" => "Nationality",
    "DisplayName" => "Nationality",
    "Type" => "dropdown",
    "Options" => "{CountryCodeMap}",
    "Default" => "IT",
    "Required" => true,
    "LangVar" => "itNationality",
];
$additionaldomainfields[".it"]["identificationnumber"] = [
    "Name" => "VATTAXPassportIDNumber",
    "DisplayName" => "VAT/TAX/Passport/ID Number",
    "Type" => "text",
    "Size" => "30",
    "Default" => "",
    "Required" => true,
    "LangVar" => "itIdentificationNumber",
];
$additionaldomainfields[".it"]["whois"] = [
    "Name" => "Hide data in public WHOIS",
    "DisplayName" => "Hide data in public WHOIS",
    "Type" => "tickbox",
    "LangVar" => "itWhois",
];
$additionaldomainfields[".it"]["terms"] = [
    "Name" => "itterms",
    "DisplayName" => "Accept .it registry <a href=\"itterms.html\" target=\"_blank\">terms and conditions</a>",
    "Type" => "tickbox",
    "Required" => true,
    "LangVar" => "itTerms",
];

$additional_it_extensions = [".ag.it", ".al.it", ".an.it", ".ao.it", ".aq.it", ".ar.it", ".ap.it", ".at.it", ".av.it", ".ba.it", ".bt.it", ".bl.it", ".bn.it", ".bg.it", ".bi.it", ".bo.it", ".bz.it", ".bs.it", ".br.it", ".ca.it", ".cl.it", ".cb.it", ".ci.it", ".ce.it", ".ct.it", ".cz.it", ".ch.it", ".co.it", ".cs.it", ".cr.it", ".kr.it", ".cn.it", ".en.it", ".fm.it", ".fe.it", ".fi.it", ".fg.it", ".fc.it", ".fr.it", ".ge.it", ".go.it", ".gr.it", ".im.it", ".is.it", ".sp.it", ".lt.it", ".le.it", ".lc.it", ".li.it", ".lo.it", ".lu.it", ".mc.it", ".mn.it", ".ms.it", ".mt.it", ".vs.it", ".me.it", ".mi.it", ".mo.it", ".mb.it", ".na.it", ".no.it", ".nu.it", ".og.it", ".ot.it", ".or.it", ".pd.it", ".pa.it", ".pr.it", ".pv.it", ".pg.it", ".pu.it", ".pe.it", ".pc.it", ".pi.it", ".pt.it", ".pn.it", ".pz.it", ".po.it", ".rg.it", ".ra.it", ".rc.it", ".re.it", ".ri.it", ".rn.it", ".rm.it", ".ro.it", ".sa.it", ".ss.it", ".sv.it", ".si.it", ".sr.it", ".so.it", ".ta.it", ".te.it", ".tr.it", ".to.it", ".tp.it", ".tn.it", ".tv.it", ".ts.it", ".ud.it", ".va.it", ".ve.it", ".vb.it", ".vc.it", ".vr.it", ".vv.it", ".vi.it", ".vt.it", ".agrigento.it", ".alessandria.it", ".ancona.it", ".aosta.it", ".laquila.it", ".arezzo.it", ".ascoli-piceno.it", ".ascolipiceno.it", ".asti.it", ".avellino.it", ".bari.it", ".barletta-andria-trani.it", ".barlettaandriatrani.it", ".belluno.it", ".benevento.it", ".bergamo.it", ".biella.it", ".bologna.it", ".bolzano.it", ".brescia.it", ".brindisi.it", ".cagliari.it", ".caltanissetta.it", ".campobasso.it", ".carbonia-iglesias.it", ".carboniaiglesias.it", ".caserta.it", ".catania.it", ".catanzaro.it", ".chieti.it", ".como.it", ".cosenza.it", ".cremona.it", ".crotone.it", ".cuneo.it", ".enna.it", ".fermo.it", ".ferrara.it", ".firenze.it", ".foggia.it", ".forli-cesena.it", ".forlicesena.it", ".frosinone.it", ".genova.it", ".gorizia.it", ".grosseto.it", ".imperia.it", ".isernia.it", ".la-spezia.it", ".latina.it", ".lecce.it", ".lecco.it", ".livorno.it", ".lodi.it", ".lucca.it", ".macerata.it", ".mantova.it", ".massa-carrara.it", ".massacarrara.it", ".matera.it", ".medio-campidano.it", ".mediocampidano.it", ".messina.it", ".milano.it", ".modena.it", ".monza-brianza.it", ".monzabrianza.it", ".napoli.it", ".novara.it", ".nuoro.it", ".ogliastra.it", ".olbia-tempio.it", ".olbiatempio.it", ".oristano.it", ".padova.it", ".palermo.it", ".parma.it", ".pavia.it", ".perugia.it", ".pesaro-urbino.it", ".pesarourbino.it", ".pescara.it", ".piacenza.it", ".pisa.it", ".pistoia.it", ".pordenone.it", ".potenza.it", ".prato.it", ".ragusa.it", ".ravenna.it", ".reggio-calabria.it", ".reggiocalabria.it", ".reggio-emilia.it", ".reggioemilia.it", ".rieti.it", ".rimini.it", ".roma.it", ".rovigo.it", ".salerno.it", ".sassari.it", ".savona.it", ".siena.it", ".siracusa.it", ".sondrio.it", ".taranto.it", ".teramo.it", ".terni.it", ".torino.it", ".trapani.it", ".trento.it", ".treviso.it", ".trieste.it", ".udine.it", ".varese.it", ".venezia.it", ".verbania.it", ".vercelli.it", ".verona.it", ".vibo-valentia.it", ".vibovalentia.it", ".vicenza.it", ".viterbo.it", ".abruzzo.it", ".basilicata.it", ".calabria.it", ".campania.it", ".emiliaromagna.it", ".friuliveneziagiulia.it", ".lazio.it", ".liguria.it", ".lombardia.it", ".marche.it", ".molise.it", ".piemonte.it", ".puglia.it", ".sardegna.it", ".sicilia.it", ".toscana.it", ".trentinoaltoadige.it", ".umbria.it", ".valledaosta.it", ".veneto.it"];
foreach ($additional_it_extensions as $extension) {
    $additionaldomainfields[$extension] = $additionaldomainfields[".it"];
}
/************** END .IT*******************/

/******** START .DE*********/
$additionaldomainfields[".de"] = [];
$additionaldomainfields[".de"][] = [
    "Name" => "tosAgree",
    "Required" => true,
    "DisplayName" => "I agree to the <a href=\"http://www.denic.de/en/bedingungen.html\" target=\"_blank\">registry terms and conditions</a>",
    "Type" => "tickbox",
    "LangVar" => "deTosAgree",
];
$additionaldomainfields[".de"][] = [
    "Name" => "role",
    "Options" => "PERSON|Person,ORG|Organization",
    "Default" => "PERSON",
    "DisplayName" => "Contact role",
    "Type" => "dropdown",
    "LangVar" => "deRole",
];
$additionaldomainfields[".de"][] = [
    "Name" => "sip",
    "DisplayName" => "SIP",
    "Type" => "text",
    "LangVar" => "deSip"
];
$additionaldomainfields[".de"][] = [
    "Name" => "fax",
    "DisplayName" => "Fax Number",
    "Type" => "text",
    "LangVar" => "deFax",
];
$additionaldomainfields[".de"][] = [
    "Name" => "Restricted Publication",
    "DisplayName" => "Hide details in WHOIS.",
    "Type" => "tickbox",
    "LangVar" => "deRestrictedPublication"
];

/******** END .DE*********/

/******** START .NL*********/
$additionaldomainfields[".nl"] = [];
$additionaldomainfields[".nl"][] = [
    "Name" => "nlTerm",
    "Required" => true,
    "DisplayName" => "I agree to the <a href=\"https://www.sidn.nl/downloads/terms-and-conditions/General Terms and Conditions for nl Registrants.pdf\" target=\"_blank\">registry terms and conditions</a>",
    "Type" => "tickbox",
    "LangVar" => "nlTerm",
];
$additionaldomainfields[".nl"][] = [
    "Name" => "nlLegalForm",
    "Options" => "BGG|Non-Dutch EC company,BRO|Non-Dutch legal form/enterprise/subsidiary,BV|Limited company,BVI/O|Limited company in formation,COOP|Cooperative,CV|Limited Partnership,EENMANSZAAK|Sole trader,EESV|European EconomicInterest Group,KERK|Religious society,MAATSCHAP|Partnership,NV|Public Company,OWM|Mutual benefit company,PERSOON|Natural person,REDR|Shipping company,STICHTING|Foundation,VERENIGING|Association,VOF|Trading partnership,ANDERS|Other",
    "Required" => true,
    "DisplayName" => "Legal Registration Form",
    "Type" => "dropdown",
    "LangVar" => "nlLeagalForm",
];
$additionaldomainfields[".nl"][] = [
    "Name" => "nlRegNumber",
    "DisplayName" => "Legal Registration Number",
    "Type" => "text",
    "LangVar" => "nlRegNumber",
];
/******** END .NL*********/

/******** START .SE *********/
$additionaldomainfields[".se"] = [];
$additionaldomainfields[".se"][] = [
    "Name" => "seregistrantidnumber",
    "DisplayName" => "Registrant ID Number",
    "Type" => "text",
    "Required" => "true",
    "LangVar" => "seRegistrantIdNumber"
];
$additionaldomainfields[".se"][] = [
    "Name" => "seregistrantvatid",
    "DisplayName" => "Registrant VAT ID",
    "Type" => "text",
    "LangVar" => "seRegistrantVatId"
];
/******** END .SE *********/

/******** START .TEL*********/
$additionaldomainfields[".tel"] = [];
$additionaldomainfields[".tel"][] = [
    "Name" => "telhostingaccount",
    "DisplayName" => "Hosting Account",
    "Type" => "text",
    "Required" => "true",
    "LangVar" => "telHostingAccount",
];
$additionaldomainfields[".tel"][] = [
    "Name" => "telhostingpassword",
    "DisplayName" => "Hosting Password",
    "Type" => "text",
    "Required" => "true",
    "LangVar" => "telHostingPassword",
];
$additionaldomainfields[".tel"][] = [
    "Name" => "telhidewhoisdata",
    "DisplayName" => "Hide details in WHOIS.",
    "Type" => "tickbox",
    "LangVar" => "telHideWhoisData",
];

/******** END .TEL*********/
/******** START .US*********/
$additionaldomainfields[".us"] = [];
$additionaldomainfields[".us"][] = [
    "Name" => "usnexuscategory",
    "DisplayName" => "Nexus Category",
    "Type" => "dropdown",
    "Options" => "C11|US Citizen,C12|US Permanent Resident,C21|US Organization,C31|Foreign Organization doing business in US,C32|Foreign Organization with US Office",
    "LangVar" => "usNexusCategory",
];
$additionaldomainfields[".us"][] = [
    "Name" => "uspurpose",
    "DisplayName" => "Application Purpose",
    "Type" => "dropdown",
    "Options" => "P3|Personal use,P1|Business for profit,P2|Non-profit,P4|Educational,P5|Governmental",
    "LangVar" => "usPurpose"
];
$additionaldomainfields[".us"][] = [
    "Name" => "usnexuscountry",
    "DisplayName" => "Nexus Country",
    "Type" => "dropdown",
    "Options" => "AF|Afghanistan,AX|Aland Islands,AL|Albania,DZ|Algeria,AS|American Samoa,AD|Andorra,AO|Angola,AI|Anguilla,AQ|Antarctica,AG|Antigua and Barbuda,AR|Argentina,AM|Armenia,AW|Aruba,AU|Australia,AT|Austria,AZ|Azerbaijan,BS|Bahamas,BH|Bahrain,BD|Bangladesh,BB|Barbados,BY|Belarus,BE|Belgium,BZ|Belize,BJ|Benin,BM|Bermuda,BT|Bhutan,BO|Bolivia,BA|Bosnia and Herzegovina,BW|Botswana,BV|Bouvet Island,BR|Brazil,IO|British Indian Ocean Territory,VG|British Virgin Islands,BN|Brunei,BG|Bulgaria,BF|Burkina Faso,BI|Burundi,KH|Cambodia,CM|Cameroon,CA|Canada,CV|Cape Verde,KY|Cayman Islands,CF|Central African Republic,TD|Chad,CL|Chile,CN|China,CX|Christmas Island,CC|Cocos (Keeling) Islands,CO|Colombia,KM|Comoros,CG|Congo,CK|Cook Islands,CR|Costa Rica,HR|Croatia,CU|Cuba,CW|Curacao,CY|Cyprus,CZ|Czech Republic,CD|Democratic Republic of Congo,DK|Denmark,DJ|Djibouti,DM|Dominica,DO|Dominican Republic,TL|East Timor,EC|Ecuador,EG|Egypt,SV|El Salvador,GQ|Equatorial Guinea,ER|Eritrea,EE|Estonia,ET|Ethiopia,FK|Falkland Islands,FO|Faroe Islands,FM|Federated States of Micronesia,FJ|Fiji,FI|Finland,FR|France,GF|French Guyana,PF|French Polynesia,TF|French Southern Territories,GA|Gabon,GM|Gambia,GE|Georgia,DE|Germany,GH|Ghana,GI|Gibraltar,GR|Greece,GL|Greenland,GD|Grenada,GP|Guadeloupe,GU|Guam,GT|Guatemala,GG|Guernsey,GN|Guinea,GW|Guinea-Bissau,GY|Guyana,HT|Haiti,HM|Heard Island and Mcdonald Islands,HN|Honduras,HK|Hong Kong,HU|Hungary,IS|Iceland,IN|India,ID|Indonesia,IR|Iran,IQ|Iraq,IE|Ireland,IM|Isle of man,IL|Israel,IT|Italy,CI|Ivory Coast,JM|Jamaica,JP|Japan,JE|Jersey,JO|Jordan,KZ|Kazakhstan,KE|Kenya,KI|Kiribati,XK|Kosovo,KW|Kuwait,KG|Kyrgyzstan,LA|Laos,LV|Latvia,LB|Lebanon,LS|Lesotho,LR|Liberia,LY|Libya,LI|Liechtenstein,LT|Lithuania,LU|Luxembourg,MO|Macau,MK|Macedonia,MG|Madagascar,MW|Malawi,MY|Malaysia,MV|Maldives,ML|Mali,MT|Malta,MH|Marshall Islands,MQ|Martinique,MR|Mauritania,MU|Mauritius,YT|Mayotte,MX|Mexico,MD|Moldova,MC|Monaco,MN|Mongolia,ME|Montenegro,MS|Montserrat,MA|Morocco,MZ|Mozambique,MM|Myanmar,NA|Namibia,NR|Nauru,NP|Nepal,NL|Netherlands,AN|Netherlands Antilles,NC|New Caledonia,NZ|New Zealand,NI|Nicaragua,NE|Niger,NG|Nigeria,NU|Niue,NF|Norfolk Island,KP|North Korea,MP|Northern Mariana Islands,NO|Norway,OM|Oman,PK|Pakistan,PW|Palau,PS|Palestinian Occupied Territories,PA|Panama,PG|Papua New Guinea,PY|Paraguay,PE|Peru,PH|Philippines,PN|Pitcairn Islands,PL|Poland,PT|Portugal,PR|Puerto Rico,QA|Qatar,RE|Reunion,RO|Romania,RU|Russia,RW|Rwanda,BL|Saint BarthГ©lemy,SH|Saint Helena and Dependencies,KN|Saint Kitts and Nevis,LC|Saint Lucia,MF|Saint Martin,PM|Saint Pierre and Miquelon,VC|Saint Vincent and the Grenadines,WS|Samoa,SM|San Marino,ST|Sao Tome and Principe,SA|Saudi Arabia,SN|Senegal,RS|Serbia,SC|Seychelles,SL|Sierra Leone,SG|Singapore,SK|Slovakia,SI|Slovenia,SB|Solomon Islands,SO|Somalia,ZA|South Africa,GS|South Georgia and South Sandwich Islands,KR|South Korea,ES|Spain,IC|Spain (Canary Islands),LK|Sri Lanka,SD|Sudan,SR|Suriname,SJ|Svalbard and Jan Mayen,SZ|Swaziland,SE|Sweden,CH|Switzerland,SY|Syria,TW|Taiwan,TJ|Tajikistan,TZ|Tanzania,TH|Thailand,TG|Togo,TK|Tokelau,TO|Tonga,TT|Trinidad and Tobago,TN|Tunisia,TR|Turkey,TM|Turkmenistan,TC|Turks And Caicos Islands,TV|Tuvalu,VI|US Virgin Islands,UG|Uganda,UA|Ukraine,AE|United Arab Emirates,GB|United Kingdom,US|United States,UM|United States Minor Outlying Islands,UY|Uruguay,UZ|Uzbekistan,VU|Vanuatu,VA|Vatican City,VE|Venezuela,VN|Vietnam,WF|Wallis and Futuna,EH|Western Sahara,YE|Yemen,ZM|Zambia,ZW|Zimbabwe",
    "LangVar" => "usNexusCountry"
];
/******** END .US*********/
