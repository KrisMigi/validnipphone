# KM Address Validation (PrestaShop 8–9)

Walidacja **NIP (PL)** i **telefonu (9 cyfr)** w formularzach: **Adres**, **Rejestracja**, **Dane osobiste** – bez JS, z użyciem Symfony Form Builder i hooków PrestaShop.

## Funkcje
- Wymaga poprawnego **NIP (PL)** z sumą kontrolną (format `PL##########` lub `##########`).
- **Firma ↔ NIP**: jeśli podajesz NIP, musisz podać firmę; jeśli podajesz firmę, NIP jest wymagany.
- **Telefon**: dokładnie **9 cyfr** (domyślnie PL +48).
- Normalizacja danych: NIP zapisywany jako `PL##########`, telefon bez spacji/myślników.
- Komunikaty błędów w BO/FO oraz logowanie zdarzeń do dziennika PrestaShop.
- Konfiguracja w BO: włącz/wyłącz walidację per formularz.

## Kompatybilność
- PrestaShop: **8.0.0 – 9.x** (testowane na 8.1.2 i 9.0.0).
- PHP: 8.1+ (zalecany 8.1–8.3).

## Instalacja
1. Skopiuj folder `kmaddressvalidation` do `/modules`.
2. W BO przejdź do **Menadżer modułów** i zainstaluj moduł.
3. Kliknij **Konfiguruj** i włącz walidacje dla wybranych formularzy.

## Ikona modułu
- Umieść plik `logo.png` w katalogu głównym modułu (`/modules/kmaddressvalidation/logo.png`).
- W razie braku ikony w BO: **Wyczyść cache** (Parametry zaawansowane → Wydajność).

## Hooki
- Adres: `actionCustomerAddressFormBuilderModifier`, `actionValidateCustomerAddressForm`, `actionObjectAddressAddBefore`, `actionObjectAddressUpdateBefore`.
- Rejestracja: `actionCustomerFormBuilderModifier`, `actionSubmitAccountBefore`.
- Dane osobiste: `actionCustomerIdentityFormBuilderModifier`, `actionBeforeCreateCustomerFormHandler`, `actionBeforeUpdateCustomerFormHandler`, `actionObjectCustomerAddBefore`, `actionObjectCustomerUpdateBefore`.

## Ograniczenia
- W formularzu **Dane osobiste** PrestaShop wymaga podania hasła do zapisu – przy niektórych motywach walidacja może prezentować błąd, a zapis mimo to przejść. Zalecane dalsze utwardzenie w handlerach *Before*/*Object* (już częściowo zaimplementowane).

## Licencja
MIT.
