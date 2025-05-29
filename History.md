# Version 1.0

## Funktionen
* SendRequest(string $room, integer $value, string $unit) sendet einen prompt an Mistral AI um eine empfehlung zu erhalten. 

## Variablen
* State: Wenn auf false, werden keine Anfragen mehr gesendet.
* Message: Variable zum anzeigen der Empfehlung in natürlichen Text.
* Recommended Value: Versteckte Variable zum anzeigen des empfohlenen Wertes.
* Unit: Versteckte Variable zum anzeigen der Masseinheit des empfohlenen Wertes.
* Room: Versteckte Variable zum anzeigen des aktuellen des betroffenen Raum.

## Konfigurationsformular
* Eingabe der Url für die Mistral AI API.
* Eingabe vom API Token.
* Liste zum eintragen der Variablen die als trigger dienen. Variablen werden registriert, somit sind keine Ereignisse als Auslöser notwendig.
* Eingabe eines Delta in Prozent um die SendRequest() zu triggern. Bedeutet, die differenz zwischen neuen und alten Wert muss >= wie der eingegebene Wert sein.