<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;

class KmAddressValidation extends Module
{
    /* ====== KLUCZE KONFIGURACJI ====== */
    const CFG_VALIDATE_ADDRESS      = 'KM_VALIDATE_ADDRESS';
    const CFG_VALIDATE_REGISTRATION = 'KM_VALIDATE_REGISTRATION';
    const CFG_VALIDATE_IDENTITY     = 'KM_VALIDATE_IDENTITY';

    public function __construct()
    {
        $this->name = 'kmaddressvalidation';
        $this->version = '1.11.8';
        $this->author = 'KM';
        $this->tab = 'front_office_features';
        $this->need_instance = 1;
        $this->bootstrap = true;
        
        parent::__construct();
        
        $this->displayName = $this->trans('KM Address Validation', [], 'Modules.Kmaddressvalidation.Shop');
        $this->description = $this->trans('Walidacja NIP (PL) i telefonu (9 cyfr) w Adresie, Rejestracji i Danych osobistych. Bez JS, natywnie przez builder + backend.', [], 'Modules.Kmaddressvalidation.Shop');
        $this->confirmUninstall = $this->trans('Czy na pewno chcesz odinstalować moduł?', [], 'Modules.Kmaddressvalidation.Shop');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        
        // Ustaw moduł jako konfigurowalny
        $this->is_configurable = true;
    }

    public function install()
    {
        // Domyślnie wyłączamy wszystkie walidacje (ustawienie 0)
        $ok =
            Configuration::updateValue(self::CFG_VALIDATE_ADDRESS, 0) &&
            Configuration::updateValue(self::CFG_VALIDATE_REGISTRATION, 0) &&
            Configuration::updateValue(self::CFG_VALIDATE_IDENTITY, 0);

        return $ok && parent::install()
            // Rejestracja hooków dla formularzy adresu, rejestracji i danych osobistych
            && $this->registerHook('actionCustomerAddressFormBuilderModifier')
            && $this->registerHook('actionValidateCustomerAddressForm')
            && $this->registerHook('actionObjectAddressAddBefore')
            && $this->registerHook('actionObjectAddressUpdateBefore')
            // Rejestracja
            && $this->registerHook('actionSubmitAccountBefore')
            && $this->registerHook('actionCustomerFormBuilderModifier')
            // Dane osobiste
            && $this->registerHook('actionCustomerIdentityFormBuilderModifier')
            && $this->registerHook('actionBeforeCreateCustomerFormHandler')
            && $this->registerHook('actionBeforeUpdateCustomerFormHandler')
            && $this->registerHook('actionObjectCustomerAddBefore')
            && $this->registerHook('actionObjectCustomerUpdateBefore');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CFG_VALIDATE_ADDRESS)
            && Configuration::deleteByName(self::CFG_VALIDATE_REGISTRATION)
            && Configuration::deleteByName(self::CFG_VALIDATE_IDENTITY);
    }

    /**
     * Generuje stronę konfiguracji modułu w panelu administracyjnym.
     */
    public function getContent()
    {
        $output = '';
        // Sprawdzenie, czy formularz został wysłany
        if (Tools::isSubmit('submitKmAddressValidation')) {
            // Pobranie wartości (0 lub 1) z formularza dla każdego ustawienia
            $valAddr = Tools::getValue(self::CFG_VALIDATE_ADDRESS, 0);
            $valReg  = Tools::getValue(self::CFG_VALIDATE_REGISTRATION, 0);
            $valId   = Tools::getValue(self::CFG_VALIDATE_IDENTITY, 0);
            // Zapisanie ustawień w bazie (tabela ps_configuration)
            Configuration::updateValue(self::CFG_VALIDATE_ADDRESS, (int)$valAddr);
            Configuration::updateValue(self::CFG_VALIDATE_REGISTRATION, (int)$valReg);
            Configuration::updateValue(self::CFG_VALIDATE_IDENTITY, (int)$valId);
            // Dodanie komunikatu potwierdzającego zapis
            $output .= $this->displayConfirmation(
                $this->trans('Settings updated', [], 'Admin.Notifications.Success')
            );
        }

        // Definicja pól formularza konfiguracji
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Ustawienia walidacji', [], 'Modules.Kmaddressvalidation.Shop'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'   => 'switch',
                        'label'  => $this->trans('Walidacja NIP i telefonu w adresach', [], 'Modules.Kmaddressvalidation.Shop'),
                        'name'   => self::CFG_VALIDATE_ADDRESS,
                        'is_bool'=> true,
                        'values' => [
                            [
                                'id'    => 'validate_address_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global')
                            ],
                            [
                                'id'    => 'validate_address_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global')
                            ],
                        ],
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->trans('Walidacja NIP i telefonu przy rejestracji', [], 'Modules.Kmaddressvalidation.Shop'),
                        'name'   => self::CFG_VALIDATE_REGISTRATION,
                        'is_bool'=> true,
                        'values' => [
                            [
                                'id'    => 'validate_reg_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global')
                            ],
                            [
                                'id'    => 'validate_reg_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global')
                            ],
                        ],
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->trans('Walidacja NIP w danych osobistych', [], 'Modules.Kmaddressvalidation.Shop'),
                        'name'   => self::CFG_VALIDATE_IDENTITY,
                        'is_bool'=> true,
                        'values' => [
                            [
                                'id'    => 'validate_id_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global')
                            ],
                            [
                                'id'    => 'validate_id_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global')
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        // Utworzenie formularza za pomocą HelperForm
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKmAddressValidation';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        // Ustawienie języka domyślnego i języka formularza
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        // Wartości domyślne (aktualne) pól formularza
        $helper->fields_value[self::CFG_VALIDATE_ADDRESS]      = Configuration::get(self::CFG_VALIDATE_ADDRESS);
        $helper->fields_value[self::CFG_VALIDATE_REGISTRATION] = Configuration::get(self::CFG_VALIDATE_REGISTRATION);
        $helper->fields_value[self::CFG_VALIDATE_IDENTITY]     = Configuration::get(self::CFG_VALIDATE_IDENTITY);

        // Zwrócenie wygenerowanego HTML formularza wraz z ewentualnym komunikatem
        return $output . $helper->generateForm([$fieldsForm]);
    }

    /* ===================== BUILDER (UX) ===================== */

    public function hookActionCustomerAddressFormBuilderModifier(array $params)
    {
        if (!$this->isAddrOn()) {
            return;
        }
        if (empty($params['form_builder'])) {
            return;
        }
        $builder = $params['form_builder'];

        // Ustawienia pola NIP (vat_number) – wzorzec 10 cyfr lub "PL" + 10 cyfr
        if ($builder->has('vat_number')) {
            $child = $builder->get('vat_number');
            $attr  = $child->getAttribute('attr') ?? [];
            $attr['pattern']   = '^(PL)?\d{10}$';
            $attr['maxlength'] = 12;
            $attr['minlength'] = 10;
            $attr['title']     = $this->msgVatPattern();
            $child->setAttribute('attr', $attr);
        }

        // Ustawienia pola telefon – dokładnie 9 cyfr
        if ($builder->has('phone')) {
            $child = $builder->get('phone');
            $attr  = $child->getAttribute('attr') ?? [];
            $attr['autocomplete'] = 'tel';
            $attr['inputmode']    = 'numeric';
            $attr['pattern']      = '^\d{9}$';
            $attr['maxlength']    = 9;
            $attr['minlength']    = 9;
            $attr['title']        = $this->trans('Telefon komórkowy: podaj dokładnie 9 cyfr. Kod kraju jest domyślnie +48 (Polska).', [], 'Modules.Kmaddressvalidation.Shop');
            $child->setAttribute('attr', $attr);
        }
    }

    /** Rejestracja – /rejestracja */
    public function hookActionCustomerFormBuilderModifier(array $params)
    {
        if (!$this->isRegOn()) {
            return;
        }
        $this->patchIdentityFormBuilder($params);
    }

    /** Dane osobiste – /dane-osobiste */
    public function hookActionCustomerIdentityFormBuilderModifier(array $params)
    {
        if (!$this->isIdOn()) {
            return;
        }
        $this->patchIdentityFormBuilder($params);
    }

    /** Znajdź nazwę pola VAT w builderze (różne szablony mogą używać różnych nazw) */
    private function resolveVatFieldNameFromBuilder($b): ?string
    {
        foreach (['siret', 'vat_number', 'company_vat', 'nip'] as $f) {
            if ($b->has($f)) {
                return $f;
            }
        }
        return null;
    }

    /** Wspólna logika dla formularzy rejestracji/tożsamości (walidacja VAT/NIP) */
    private function patchIdentityFormBuilder(array $params): void
    {
        if (empty($params['form_builder'])) {
            return;
        }
        /** @var \Symfony\Component\Form\FormBuilderInterface $b */
        $b = $params['form_builder'];

        $vatField = $this->resolveVatFieldNameFromBuilder($b);
        if (!$vatField) {
            return;
        }

        $child   = $b->get($vatField);
        $type    = get_class($child->getType()->getInnerType());
        $options = $child->getOptions();

        // Atrybuty HTML (UX) dla pola NIP
        $attr = isset($options['attr']) ? $options['attr'] : [];
        $attr['inputmode']    = 'numeric';
        $attr['autocomplete'] = 'tax-id';
        $attr['pattern']      = '^(PL)?\d{10}$';
        $attr['maxlength']    = 12;
        $attr['minlength']    = 10;
        $attr['title']        = $this->msgVatPattern();
        $options['attr']      = $attr;

        // Constraint – zależność Firma ↔ NIP (oba pola muszą być uzupełnione razem)
        $constraints = isset($options['constraints']) ? (array)$options['constraints'] : [];
        $constraints[] = new Assert\Callback(function ($value, ExecutionContextInterface $context) {
            $root    = $context->getRoot();
            $company = $root->has('company') ? (string)$root->get('company')->getData() : (string)\Tools::getValue('company','');
            $entered = (string)($value ?? '');

            if (trim($company) !== '' && trim($entered) === '') {
                $context->buildViolation($this->msgVatRequired())->addViolation();
                return;
            }
            if (trim($entered) !== '' && trim($company) === '') {
                $context->buildViolation($this->msgCompanyRequiredForVat())->addViolation();
                return;
            }
            if (trim($entered) !== '') {
                $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper($entered));
                if (!$chk['valid']) {
                    $context->buildViolation($chk['message'])->addViolation();
                }
            }
        });
        $options['constraints'] = $constraints;

        // Ponowne dodanie (nadpisanie) pola VAT/NIP z nowymi opcjami
        $b->add($vatField, $type, $options);

        // PRE_SUBMIT – walidacja przed złożeniem formularza (twarde blokady)
        $b->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($vatField) {
            $data    = (array)$event->getData();
            $company = isset($data['company']) ? trim((string)$data['company']) : '';
            $vat     = isset($data[$vatField]) ? (string)$data[$vatField] : '';

            if ($company !== '' && trim($vat) === '') {
                // Firma podana, brak NIP
                $event->getForm()->get($vatField)->addError(new FormError($this->msgVatRequired()));
                $this->clearSuccessFlash();
                $event->stopPropagation();
                return;
            }
            if (trim($vat) !== '' && $company === '' && $event->getForm()->has('company')) {
                // NIP podany, brak nazwy firmy
                $event->getForm()->get('company')->addError(new FormError($this->msgCompanyRequiredForVat()));
                $this->clearSuccessFlash();
                $event->stopPropagation();
                return;
            }
            if (trim($vat) !== '') {
                // Jeśli NIP podany, sprawdzamy jego poprawność
                $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper($vat));
                if (!$chk['valid']) {
                    $event->getForm()->get($vatField)->addError(new FormError($chk['message']));
                    $this->clearSuccessFlash();
                    $event->stopPropagation();
                }
            }
        });

        // SUBMIT – normalizacja poprawnej wartości NIP (dodanie "PL" i usunięcie znaków, jeśli walidacja ok)
        $b->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($vatField) {
            $data = (array)$event->getData();
            if (!empty($data[$vatField])) {
                $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper((string)$data[$vatField]));
                if ($chk['valid']) {
                    // Zamiana wartości na znormalizowaną (np. dodanie "PL" przed 10 cyfr)
                    $data[$vatField] = $chk['normalized'];
                    $event->setData($data);
                }
            }
        });

        // POST_SUBMIT – dodatkowa weryfikacja po złożeniu formularza (podwójne zabezpieczenie)
        $b->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($vatField) {
            $form = $event->getForm();
            if (!$form->has($vatField)) {
                return;
            }

            $company = $form->has('company') ? (string)$form->get('company')->getData() : '';
            $vat     = (string)$form->get($vatField)->getData();

            if (trim($company) !== '' && trim($vat) === '') {
                // Firma jest, NIP pusty
                $form->get($vatField)->addError(new FormError($this->msgVatRequired()));
            } elseif (trim($vat) !== '' && trim($company) === '') {
                // NIP jest, firma pusta
                if ($form->has('company')) {
                    $form->get('company')->addError(new FormError($this->msgCompanyRequiredForVat()));
                } else {
                    // Gdyby pole 'company' nie istniało (teoretycznie nie powinno się zdarzyć)
                    $form->get($vatField)->addError(new FormError($this->msgCompanyRequiredForVat()));
                }
            } elseif (trim($vat) !== '') {
                // Jeśli NIP podano, finalna weryfikacja poprawności
                $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper($vat));
                if (!$chk['valid']) {
                    $form->get($vatField)->addError(new FormError($chk['message']));
                }
            }
        });
    }

    /* ===================== ADRES – WALIDACJA FORMULARZA ===================== */

    public function hookActionValidateCustomerAddressForm(array $params)
    {
        if (!$this->isAddrOn()) {
            return;
        }

        $form = isset($params['form']) ? $params['form'] : null;
        $company  = trim((string)\Tools::getValue('company', ''));
        $vatRaw   = (string)\Tools::getValue('vat_number', '');
        $phoneRaw = (string)\Tools::getValue('phone', '');

        // Jeśli podano NIP bez firmy – błąd
        if ($vatRaw !== '' && $company === '') {
            $this->addFormError($form, 'company', $this->msgCompanyRequiredForVat());
        }

        // Jeśli podano NIP – sprawdź i sformatuj; jeśli firma bez NIP – błąd
        if ($vatRaw !== '') {
            $vatSan     = $this->sanitizeSpacesDashesUpper($vatRaw);
            $digitsOnly = preg_replace('/\D+/', '', $vatSan);

            // Dodanie prefiksu PL, jeśli brak
            if ($digitsOnly !== '' && !preg_match('/^[A-Z]/', $vatSan)) {
                $this->setFormValue($form, 'vat_number', 'PL' . $digitsOnly);
            } elseif (strpos($vatSan, 'PL') === 0) {
                // Jeśli zaczyna się od "PL", zapewnij że format jest "PLxxxxxxxxxx"
                $this->setFormValue($form, 'vat_number', 'PL' . $digitsOnly);
            } else {
                $this->setFormValue($form, 'vat_number', $vatSan);
            }

            // Walidacja sumy kontrolnej NIP
            $vatCheck = $this->validatePolishVat($vatSan);
            if (!$vatCheck['valid']) {
                $this->addFormError($form, 'vat_number', $vatCheck['message']);
            }
        } elseif ($company !== '') {
            // Podano firmę bez NIP – błąd
            $this->addFormError($form, 'vat_number', $this->msgVatRequired());
        }

        // Walidacja i normalizacja telefonu (opcjonalnie)
        if ($phoneRaw !== '') {
            $phoneSan = $this->sanitizeSpacesDashes($phoneRaw);
            $this->setFormValue($form, 'phone', $phoneSan);
            if (!ctype_digit($phoneSan) || strlen($phoneSan) !== 9) {
                $this->addFormError($form, 'phone', 
                    $this->trans('Telefon komórkowy: podaj dokładnie 9 cyfr. Kod kraju jest domyślnie +48 (Polska).', [], 'Modules.Kmaddressvalidation.Shop')
                );
            }
        }
    }

    /* ===================== ADRES – TWARDY BEZPIECZNIK (pre-hook przed zapisem adresu) ===================== */

    public function hookActionObjectAddressAddBefore(array $params)
    {
        if ($this->isAddrOn()) {
            $this->normalizeAddressObject($params);
        }
    }
    public function hookActionObjectAddressUpdateBefore(array $params)
    {
        if ($this->isAddrOn()) {
            $this->normalizeAddressObject($params);
        }
    }

    private function normalizeAddressObject(array $params): void
    {
        if (empty($params['object']) || !is_object($params['object'])) {
            return;
        }
        $address = $params['object'];

        // Normalizacja NIP w obiekcie Address (przed zapisem do bazy)
        if (isset($address->vat_number) && $address->vat_number !== '') {
            $vatSan   = $this->sanitizeSpacesDashesUpper((string)$address->vat_number);
            $vatCheck = $this->validatePolishVat($vatSan);
            if ($vatCheck['valid']) {
                // Zapis znormalizowanego NIP (dodany "PL" i bez spacji)
                $address->vat_number = $vatCheck['normalized'];
            } else {
                // Błąd – niepoprawny NIP, przerwanie zapisu adresu
                $this->log('address_save_blocked type=vat msg='.$vatCheck['message'].' raw='.(string)$address->vat_number);
                if (isset($this->context->controller)) {
                    $this->context->controller->errors[] = $vatCheck['message'];
                }
            }
        }

        // Normalizacja telefonu w obiekcie Address (usuniecie spacji, myślników)
        if (isset($address->phone) && $address->phone !== '') {
            $san = $this->sanitizeSpacesDashes((string)$address->phone);
            if (ctype_digit($san) && strlen($san) === 9) {
                $address->phone = $san;
            } else {
                // Błąd – niepoprawny telefon, przerwanie zapisu adresu
                $this->log('address_save_blocked type=phone raw='.(string)$address->phone);
                if (isset($this->context->controller)) {
                    $this->context->controller->errors[] = 
                        $this->trans('Telefon komórkowy: wpisz dokładnie 9 cyfr. Domyślny kod kraju: +48 (Polska).', [], 'Modules.Kmaddressvalidation.Shop');
                }
            }
        }
    }

    /* ===================== REJESTRACJA – WALIDACJA BACKEND ===================== */

    public function hookActionSubmitAccountBefore(array $params)
    {
        if (!$this->isRegOn()) {
            return true;
        }

        $errors   = false;
        $company  = trim((string)\Tools::getValue('company', ''));
        list($vatKey, $rawVat) = $this->extractVatFromRequest(['siret', 'vat_number']);

        // Jeśli podano NIP bez firmy – błąd
        if (!empty($rawVat) && $company === '') {
            $this->context->controller->errors[] = $this->msgCompanyRequiredForVat();
            $this->clearSuccessFlash();
            $errors = true;
        }

        if ($rawVat !== null) {
            // Walidacja NIP
            $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper($rawVat));
            if (!$chk['valid']) {
                $this->context->controller->errors[] = $chk['message'];
                $this->clearSuccessFlash();
                $this->log('account_submit_error type=vat msg='.$chk['message'].' raw='.$rawVat);
                $errors = true;
            } else {
                // Wstawienie znormalizowanego NIP do danych wysyłanych ($_POST)
                $_POST[$vatKey] = $_REQUEST[$vatKey] = $chk['normalized'];
            }
        } elseif ($company !== '') {
            // Firma podana bez NIP – błąd
            $this->context->controller->errors[] = $this->msgVatRequired();
            $this->clearSuccessFlash();
            $errors = true;
        }

        // Walidacja telefonu (jeśli podano)
        $phoneRaw = (string)\Tools::getValue('phone', '');
        if ($phoneRaw !== '') {
            $san = $this->sanitizeSpacesDashes($phoneRaw);
            if (!ctype_digit($san) || strlen($san) !== 9) {
                $this->context->controller->errors[] = 
                    $this->trans('Telefon komórkowy: wpisz dokładnie 9 cyfr. Domyślny kod kraju: +48 (Polska).', [], 'Modules.Kmaddressvalidation.Shop');
                $this->clearSuccessFlash();
                $this->log('account_submit_error type=phone raw='.$phoneRaw);
                $errors = true;
            } else {
                // Zapis oczyszczonego telefonu do $_POST (żeby nadpisać ewentualne spacje/myślniki)
                $_POST['phone'] = $_REQUEST['phone'] = $san;
            }
        }

        return $errors ? false : true;
    }

    /* ===================== DANE OSOBISTE – WALIDACJA BACKEND (przed zapisem) ===================== */

    public function hookActionBeforeCreateCustomerFormHandler(array $params)
    {
        if ($this->isIdOn()) {
            $this->handleCustomerFormDataBefore($params);
        }
    }
    public function hookActionBeforeUpdateCustomerFormHandler(array $params)
    {
        if ($this->isIdOn()) {
            $this->handleCustomerFormDataBefore($params);
        }
    }

    private function handleCustomerFormDataBefore(array $params): void
    {
        $company = trim((string)\Tools::getValue('company', ''));
        $siret   = (string)\Tools::getValue('siret', '');  // 'siret' w Presta jest używane dla NIP

        // Jeśli podano NIP bez firmy – błąd
        list($vatKey, $vatRaw) = $this->extractVatFromRequest(['siret', 'vat_number', 'nip', 'company_vat']);
        if (!empty($vatRaw) && $company === '') {
            $this->context->controller->errors[] = $this->msgCompanyRequiredForVat();
            $this->clearSuccessFlash();
            $this->log('customer_form_error type=vat_without_company raw='.$vatRaw);
            return; // przerwanie zapisu (błąd)
        }

        // Jeśli podano firmę bez NIP – błąd
        if ($company !== '' && trim($siret) === '') {
            $this->context->controller->errors[] = $this->msgVatRequired();
            $this->clearSuccessFlash();
            $this->log('customer_form_error type=company_without_vat');
            return; // przerwanie zapisu (błąd)
        }

        // Walidacja i normalizacja NIP (jeśli podano)
        if ($vatKey !== null) {
            $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper($vatRaw));
            if (!$chk['valid']) {
                $this->context->controller->errors[] = $chk['message'];
                $this->clearSuccessFlash();
                $this->log('customer_form_error type=vat key='.$vatKey.' raw='.$vatRaw.' msg='.$chk['message']);
                return; // przerwanie zapisu (błąd)
            }
            // Nadpisanie znormalizowanym NIP w danych formularza
            $_POST[$vatKey] = $_REQUEST[$vatKey] = $chk['normalized'];
            if (isset($params['form_data']) && is_array($params['form_data']) && array_key_exists($vatKey, $params['form_data'])) {
                $params['form_data'][$vatKey] = $chk['normalized'];
            }
        }
    }

    /* ===================== DANE OSOBISTE/REJESTRACJA – TWARDY BEZPIECZNIK OBIEKTU CUSTOMER ===================== */

    public function hookActionObjectCustomerAddBefore(array $params)
    {
        $this->guardCustomerObject($params);
    }
    public function hookActionObjectCustomerUpdateBefore(array $params)
    {
        $this->guardCustomerObject($params);
    }

    private function guardCustomerObject(array $params): void
    {
        // Jeśli obie walidacje (rejestracja i tożsamość) są wyłączone – nic nie rób
        if (!$this->isRegOn() && !$this->isIdOn()) {
            return;
        }
        if (empty($params['object']) || !is_object($params['object'])) {
            return;
        }
        $customer = $params['object'];

        // Jeśli podano nazwę firmy bez NIP – błąd (nie pozwól zapisać)
        if (isset($customer->company) && trim((string)$customer->company) !== '') {
            $hasVat = isset($customer->siret) && trim((string)$customer->siret) !== '';
            if (!$hasVat && isset($this->context->controller)) {
                $this->context->controller->errors[] = $this->msgVatRequired();
                $this->clearSuccessFlash();
                return; // zapis zablokowany
            }
        }

        // Jeśli podano NIP bez nazwy firmy – błąd
        if (isset($customer->siret) && trim((string)$customer->siret) !== '' &&
            (!isset($customer->company) || trim((string)$customer->company) === '')
        ) {
            if (isset($this->context->controller)) {
                $this->context->controller->errors[] = $this->msgCompanyRequiredForVat();
                $this->clearSuccessFlash();
            }
            $this->log('customer_save_blocked type=siret_without_company raw='.(string)$customer->siret);
            return; // zapis zablokowany
        }

        // Walidacja i normalizacja NIP (jeśli podano)
        if (isset($customer->siret) && $customer->siret !== '') {
            $chk = $this->validatePolishVat($this->sanitizeSpacesDashesUpper((string)$customer->siret));
            if ($chk['valid']) {
                $customer->siret = $chk['normalized'];
            } else {
                $this->log('customer_save_blocked type=siret msg='.$chk['message'].' raw='.(string)$customer->siret);
                if (isset($this->context->controller)) {
                    $this->context->controller->errors[] = $chk['message'];
                }
                return; // zapis zablokowany
            }
        }
    }

    /* ===================== FUNKCJE POMOCNICZE ===================== */

    // Walidacja polskiego NIP (sprawdzenie formatu i sumy kontrolnej)
    private function validatePolishVat(string $raw): array
    {
        $raw = strtoupper($raw);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($raw === '') {
            return ['valid' => false, 'message' =>
                $this->trans('Podaj NIP (PL). Sprzedajemy i fakturujemy wyłącznie w Polsce.', [], 'Modules.Kmaddressvalidation.Shop')
            ];
        }

        // Odrzuć NIP z samymi powtarzającymi się cyframi (000... lub 111... itd.)
        if ($digits === '0000000000' || preg_match('/^(\d)\1{9}$/', $digits)) {
            $this->log('invalid_vat_pattern raw='.$raw);
            return ['valid' => false, 'message' => $this->msgVatTenDigits()];
        }

        // Sprawdzenie poprawności prefiksu PL (jeśli występuje)
        if (strpos($raw, 'PL') === 0) {
            if (!ctype_digit(substr($raw, 2))) {
                return ['valid' => false, 'message' =>
                    $this->trans('Nieprawidłowy NIP. Po prefiksie PL podaj dokładnie 10 cyfr, bez spacji i znaków.', [], 'Modules.Kmaddressvalidation.Shop')
                ];
            }
        } else {
            if (preg_match('/[A-Z]/', $raw)) {
                return ['valid' => false, 'message' =>
                    $this->trans('Nieprawidłowy NIP. Dozwolone formaty: PLXXXXXXXXXX lub XXXXXXXXXX (10 cyfr). Inne litery/prefiksy są niedozwolone.', [], 'Modules.Kmaddressvalidation.Shop')
                ];
            }
        }

        if (strlen($digits) !== 10) {
            return ['valid' => false, 'message' => $this->msgVatTenDigits()];
        }

        // Weryfikacja sumy kontrolnej NIP
        $wagi = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $wagi[$i] * (int)$digits[$i];
        }
        $ctrl = $sum % 11;
        if ($ctrl === 10 || $ctrl !== (int)$digits[9]) {
            return ['valid' => false, 'message' => $this->msgVatChecksum()];
        }

        // Jeśli wszystko OK – zwróć znormalizowany NIP (dodany prefiks PL)
        return ['valid' => true, 'message' => '', 'normalized' => 'PL' . $digits];
    }

    // Usunięcie spacji, myślników, kropek i zamiana liter na wielkie (dla NIP)
    private function sanitizeSpacesDashesUpper(string $s): string
    {
        return strtoupper($this->sanitizeSpacesDashes($s));
    }
    // Usunięcie spacji, myślników, kropek (dla telefonu/NIP)
    private function sanitizeSpacesDashes(string $s): string
    {
        return preg_replace('/[\s\-\.]+/', '', $s);
    }

    // Ustawienie wartości pola formularza (jeśli istnieje)
    private function setFormValue($form, string $fieldName, string $value): void
    {
        if ($form && method_exists($form, 'getField')) {
            $field = $form->getField($fieldName);
            if ($field && method_exists($field, 'setValue')) {
                $field->setValue($value);
            }
        }
    }

    // Dodanie błędu do pola formularza lub kontrolera (fallback)
    private function addFormError($form, string $fieldName, string $message): void
    {
        if ($form && method_exists($form, 'getField')) {
            $field = $form->getField($fieldName);
            if ($field && method_exists($field, 'addError')) {
                $field->addError($message);
                $this->log('form_error field=' . $fieldName . ' msg=' . $message);
                $this->clearSuccessFlash();
                return;
            }
        }
        if (isset($this->context->controller)) {
            $this->context->controller->errors[] = $message;
            $this->clearSuccessFlash();
        }
        $this->log('form_error[FALLBACK] field=' . $fieldName . ' msg=' . $message);
    }

    // Logger – zapis do logów PrestaShop (widoczne w Parametry zaawansowane > Logi)
    private function log(string $msg): void
    {
        PrestaShopLogger::addLog('[km][shop=' . (int)Context::getContext()->shop->id . '] ' . $msg, 2, null, 'Module', (int)$this->id, true);
    }

    // Komunikaty błędów/wskazówek (tłumaczenia)
    private function msgVatRequired(): string {
        return $this->trans('Dla podanej nazwy firmy NIP jest wymagany. Podaj 10 cyfr lub PL + 10 cyfr.', [], 'Modules.Kmaddressvalidation.Shop');
    }
    private function msgCompanyRequiredForVat(): string {
        return $this->trans('Jeśli podajesz NIP, musisz również podać nazwę firmy.', [], 'Modules.Kmaddressvalidation.Shop');
    }
    private function msgVatPattern(): string {
        return $this->trans('NIP (Polska): 10 cyfr (np. 0060070008) lub PL + 10 cyfr (np. PL0060070008).', [], 'Modules.Kmaddressvalidation.Shop');
    }
    private function msgVatTenDigits(): string {
        return $this->trans('Nieprawidłowy NIP. Wymagane jest dokładnie 10 cyfr (np. PL0060070008 lub 0060070008).', [], 'Modules.Kmaddressvalidation.Shop');
    }
    private function msgVatChecksum(): string {
        return $this->trans('Nieprawidłowy NIP (błędna suma kontrolna).', [], 'Modules.Kmaddressvalidation.Shop');
    }

    // Pobranie pierwszego niepustego pola NIP z danych $_REQUEST (różne pola zależnie od szablonu)
    private function extractVatFromRequest(array $keys = ['siret', 'vat_number', 'nip', 'company_vat']): array {
        foreach ($keys as $k) {
            $v = (string)\Tools::getValue($k, '');
            if ($v !== '') {
                return [$k, $v];
            }
        }
        return [null, null];
    }

    // Wyczyszczenie komunikatów "success" (żeby nie pokazywały się zielone powiadomienia po błędzie)
    private function clearSuccessFlash(): void {
        if (isset($this->context->controller) && property_exists($this->context->controller, 'success')) {
            $this->context->controller->success = [];
        }
    }

    // Sprawdzenie czy dana walidacja jest włączona (na podstawie konfiguracji)
    private function isAddrOn(): bool {
        return Configuration::get(self::CFG_VALIDATE_ADDRESS) == 1;
    }
    private function isRegOn(): bool {
        return Configuration::get(self::CFG_VALIDATE_REGISTRATION) == 1;
    }
    private function isIdOn(): bool {
        return Configuration::get(self::CFG_VALIDATE_IDENTITY) == 1;
    }
}
