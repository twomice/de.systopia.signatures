<?php
/*------------------------------------------------------------+
| SYSTOPIA Signatures                                         |
| Copyright (C) 2017 SYSTOPIA                                 |
| Author: B. Endres (endres@systopia.de)                      |
|         J. Schuppe (schuppe@systopia.de)                    |
+-------------------------------------------------------------+
| This program is released as free software under the         |
| Affero GPL license. You can redistribute it and/or          |
| modify it under the terms of this license which you         |
| can read by viewing the included agpl.txt or online         |
| at www.gnu.org/licenses/agpl.html. Removal of this          |
| copyright header is strictly prohibited without             |
| written permission from the original author(s).             |
+-------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Signatures_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Signatures_Form_Signatures extends CRM_Core_Form {

  /**
   * @var \CRM_Signatures_Signatures
   *   The set of signatures for the contact.
   */
  protected $signatures;

  /**
   * Builds the form.
   */
  public function buildQuickForm(): void {
    $contact_id = (string) $this->setContactID();

    $signatures = CRM_Signatures_Signatures::getSignatures($contact_id);
    if (NULL !== $signatures) {
      $this->signatures = $signatures;
    }
    else {
      $this->signatures = new CRM_Signatures_Signatures($contact_id);
    }

    // Add form elements.
    $this->add(
      'wysiwyg',
      'signature_letter_html',
      E::ts('Letter signature (HTML)')
    );
    $this->add(
      'wysiwyg',
      'signature_email_html',
      E::ts('E-mail signature (HTML)')
    );
    $this->add(
      'textarea',
      'signature_email_plain',
      E::ts('E-mail signature (plain text)'),
      ['rows' => '10']
    );
    $this->add(
      'wysiwyg',
      'signature_mass_mailing_html',
      E::ts('Mass mailing signature (HTML)')
    );
    $this->add(
      'textarea',
      'signature_mass_mailing_plain',
      E::ts('Mass mailing signature (plain text)'),
      ['rows' => '10']
    );
    $this->add(
      'wysiwyg',
      'signature_additional_html',
      E::ts('Additional signature (HTML)')
    );
    $this->add(
      'textarea',
      'signature_additional_plain',
      E::ts('Additional signature (plain text)'),
      ['rows' => '10']
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ],
    ]);

    // Export form elements.
    $this->assign('elementNames', $this->getRenderableElementNames());
    $this->assign('contactID', $contact_id);

    if ($contact_id == CRM_Core_Session::getLoggedInContactID()) {
      $header = E::ts('You are editing signatures for yourself (contact ID <em>%1</em>).', array(
        'domain' => 'de.systopia.signatures',
        1 => $contact_id,
      ));
    }
    else {
      $display_name = $contacts = \Civi\Api4\Contact::get(TRUE)
        ->addSelect('display_name')
        ->addWhere('id', '=', $contact_id)
        ->setLimit(1)
        ->execute()
        ->first()['display_name'];
      $header = E::ts('You are editing signatures for the contact <em>%1</em> (contact ID <em>%2</em>).', array(
        'domain' => 'de.systopia.signatures',
        1 => $display_name,
        2 => $contact_id,
      ));
    }
    $this->assign('header', $header);

    parent::buildQuickForm();
  }

  /**
   * Set the default values (signatures' current data) in the form.
   *
   * @return array<string, string>
   */
  public function setDefaultValues(): array {
    $defaults = parent::setDefaultValues();

    $defaults['contact_id'] = $this->signatures->getContactID();
    foreach ($this->signatures->getData() as $signature_name => $signature_body) {
      $defaults[$signature_name] = $signature_body;
    }
    return $defaults;
  }

  /**
   * Validate the submitted form.
   *
   * @return bool
   */
  public function validate(): bool {
    $values = $this->exportValues();
    $contact_id = (string) $this->getContactID();

    try {
      $this->signatures->setContactID($contact_id);
      foreach ($this->signatures->getData() as $signature_name => $signature_body) {
        if (isset($values[$signature_name])) {
          $this->signatures->setSignature($signature_name, $values[$signature_name]);
        }
      }
      $this->signatures->verifySignatures();
    }
    catch (Exception $exception) {
      // @ignoreException
      switch ($exception->getCode()) {
        case 'signatures_too_long':
          $this->setElementError(
            'buttons',
            E::ts('The signatures are too long. Please shorten at least one signature.')
          );
          break;

        default:
          $this->setElementError(
            'buttons',
            E::ts('An error occurred, please reload the form.')
          );
          break;
      }
    }

    return parent::validate();
  }

  /**
   * Processes the submitted form.
   */
  public function postProcess(): void {
    $values = $this->exportValues();
    $contact_id = (string) $this->getContactID();

    $this->signatures->setContactID($contact_id);
    foreach ($this->signatures->getData() as $signature_name => $signature_body) {
      if (isset($values[$signature_name])) {
        $this->signatures->setSignature($signature_name, $values[$signature_name]);
      }
    }
    $this->signatures->saveSignatures();

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return list<string>
   */
  public function getRenderableElementNames(): array {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_element $element */
      $label = $element->getLabel();
      if ('' !== $label) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
