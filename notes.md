# Notes.md

#### (This is a compendium of notes and useful things I want to keep while developing this.)

* The `WhoAmI` SOAP XML looks like:

```xml
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope">
   <soap:Body>
       <WhoAmI/>
   </soap:Body>
</soap:Envelope>
```

<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><WhoAmI><name>Remy Blom</name><msg>Hi!</msg></WhoAmI></soap:Body></soap:Envelope>
