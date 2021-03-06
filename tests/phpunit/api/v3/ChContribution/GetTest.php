<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * CHContribution.Get API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_CHContribution_GetTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

  use Civi\Test\Api3TestTrait;
  use Civi\Test\ContactTestTrait;

  protected $fund;

  protected $fund2;

  protected $customGroup;

  protected $customField;

  /**
   * Should we destroy the custom fields that we create or not
   * @var bool
   */
  protected $tearDownCustomField = TRUE;

  /**
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   * See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * The setup() method is executed before the test is executed (optional).
   */
  public function setUp() {
    parent::setUp();
    // If the ch_fund option group is no longer present (likely from the removal of the custom field in the taredown. recreate it
    $optionGroup = $this->callAPISuccess('OptionGroup', 'get', ['name' => 'ch_fund']);
    if (empty($optionGroup['count'])) {
      $optionGroupNew = $this->callAPISuccess('OptionGroup', 'create', [
        'title' => 'CH Fund',
        'name' => 'ch_fund',
        'data_type' => 'String',
        'description' => '',
        'is_active' => 1,
        'is_reserved' => 1,
      ]);
      // Ensure that the civicrm_managed table is also updated just in case.
      CRM_Core_DAO::executeQuery("UPDATE civicrm_managed SET entity_id = %1 WHERE entity_type = 'OptionGroup' AND module = 'biz.jmaconsulting.chfunds'", [1 => [$optionGroupNew['id'], 'Positive']]);
    }
    $optionGroup = $this->callAPISuccess('OptionGroup', 'get', ['name' => 'ch_fund']);
    $customFieldCheck = $this->callAPISuccess('CustomField', 'get', [
      'option_group_id' => 'ch_fund',
    ]);
    if (empty($customFieldCheck['count'])) {
      $this->customGroup = $this->callAPISuccess('CustomGroup', 'create', [
        'title' => 'Additional info',
        'extends' => 'Contribution',
        'collapse_display' => 1,
        'is_public' => 1,
        'is_active' => 1,
      ]);
      $this->customField = $this->callAPISuccess('CustomField', 'create', [
        'custom_group_id' => $this->customGroup['id'],
        'label' => 'CH Fund',
        'name' => 'Fund',
        'data_type' => 'String',
        'option_group_id' => 'ch_fund',
        'is_searchable' => 1,
        'is_active' => 1,
        'html_type' => 'ContactReference',
      ]);
    }
    else {
      $this->tearDownCustomField = FALSE;
    }
    $this->fund = $this->callAPISuccess('FinancialType', 'create', [
      'label' => 'Test Created Fund',
      'name' => 'test_created_fund',
      'is_deductible' => 1,
    ]);
    $this->unallocatedFund = $this->callAPISuccess('FinancialType', 'get', ['name' => 'Unassigned CH Fund']);
  }

  public function tearDown() {
    parent::tearDown();
    if ($this->tearDownCustomField) {
      $this->callAPISuccess('CustomField', 'delete', ['id' => $this->customField['id']]);
      $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroup['id']]);
    }
    $this->callAPISuccess('FinancialType', 'get', ['id' => $this->fund['id'], 'api.FinancialType.delete' => '"id":"$value.id"']);
  }

  /**
   * Simple example test case.
   *
   * Note how the function name begins with the word "test".
   */
  public function testGetCHFundContribution() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    $contact = $this->individualCreate();
    $contribution = $this->callAPISuccess('CHContribution', 'create', [
      'contact_id' => $contact,
      'ch_fund' => 'CH+99999',
      'payment_instrument_id' => 'Credit Card',
      'total_amount' => '100',
    ]);
    $getResult = $this->callAPISuccess('Contribution', 'get', ['return' => ['custom_' . $this->customField['id']], 'id' => $contribution['id']]);
    // Confirm that the custom field has been correctly populated and that the correct financial type was assigned.
    $this->assertEquals($this->unallocatedFund['id'], $contribution['values'][$contribution['id']]['financial_type_id']);
    $this->assertEquals('CH+99999', $getResult['values'][$getResult['id']]['custom_' . $this->customField['id']]);
    $chContributionGet = $this->callAPISuccess('CHContribution', 'get', [
      'ch_fund' => 'CH+99999',
    ]);
    $this->assertEquals($contact, $chContributionGet['values'][$chContributionGet['id']]['contact_id']);
    $this->assertEquals($this->unallocatedFund['id'], $chContributionGet['values'][$chContributionGet['id']]['financial_type_id']);
    $this->callAPISuccess('Contribution', 'delete', ['id' => $contribution['id']]);
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
  }

}
