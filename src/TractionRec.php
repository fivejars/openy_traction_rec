<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\openy_traction_rec\QueryBuilder\SelectQuery;

/**
 * TractionRec API wrapper.
 */
class TractionRec implements TractionRecInterface {

  /**
   * Traction Rec Client service.
   */
  protected TractionRecClient $tractionRecClient;

  /**
   * The Traction Rec settings.
   */
  protected ImmutableConfig $tractionRecSettings;

  /**
   * Logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * TractionRec constructor.
   *
   * @param \Drupal\openy_traction_rec\TractionRecClient $traction_rec_client
   *   The TractionRec API client.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   Logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(TractionRecClient $traction_rec_client, LoggerChannelInterface $loggerChannel, ConfigFactoryInterface $config_factory) {
    $this->tractionRecClient = $traction_rec_client;
    $this->logger = $loggerChannel;
    $this->tractionRecSettings = $config_factory->get('openy_traction_rec.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function loadLocations(): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Location__c');
      $query->addField('TREX1__Location__c.id');
      $query->addField('TREX1__Location__c.name');
      $query->addField('TREX1__Location__c.TREX1__Address_City__c');
      $query->addField('TREX1__Location__c.TREX1__Address_Country__c');
      $query->addField('TREX1__Location__c.TREX1__Address_State__c');
      $query->addField('TREX1__Location__c.TREX1__Address_Street__c');
      $query->addField('TREX1__Location__c.TREX1__Address_Postal_Code__c');

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of locations", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCourses(): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Course__c');
      $query->addField('TREX1__Course__c.id');
      $query->addField('TREX1__Course__c.name');
      $query->addField('TREX1__Course__c.TREX1__Description__c');
      $query->addField('TREX1__Course__c.TREX1__Rich_Description__c');
      $query->addField('TREX1__Course__c.TREX1__Program__r.id');
      $query->addField('TREX1__Course__c.TREX1__Program__r.name');
      $query->addField('TREX1__Course__c.TREX1__Available__c');
      $query->addCondition('TREX1__Course__c.TREX1__Available_Online__c', 'true');

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of courses", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCourseSessions(): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Course_Session__c');
      $query->addField('TREX1__Course_Session__c.id');
      $query->addField('TREX1__Course_Session__c.name');
      $query->addField('TREX1__Course_Session__c.TREX1__Description__c');
      $query->addField('TREX1__Course_Session__c.TREX1__Rich_Description__c');
      $query->addField('TREX1__Course_Session__c.TREX1__Available__c');
      $query->addField('TREX1__Course_Session__c.TREX1__Course__r.id');
      $query->addField('TREX1__Course_Session__c.TREX1__Course__r.name');
      $query->addCondition('TREX1__Course_Session__c.TREX1__Available_Online__c', 'true');

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of course sessions", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadProgramCategoryTags(): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Program_Category_Tag__c');
      $query->addField('TREX1__Program_Category_Tag__c.id');
      $query->addField('TREX1__Program_Category_Tag__c.name');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program__r.id');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program__r.name');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program__r.TREX1__Available__c');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.id');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.name');
      $query->addField('TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.TREX1__Available__c');
      $query->addCondition('TREX1__Program__r.TREX1__Available_Online__c', 'true');
      $query->addCondition('TREX1__Program_Category__r.TREX1__Available_Online__c', 'true');

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of program category tags", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCourseOptions(array $locations = []): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Course_Session_Option__c');
      $query->addField('TREX1__Course_Option__r.id');
      $query->addField('TREX1__Course_Option__r.name');
      $query->addField('TREX1__Course_Option__r.TREX1__Available_Online__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Available__c');
      $query->addField('TREX1__Course_Option__r.TREX1__capacity__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Start_Date__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Start_Time__c');
      $query->addField('TREX1__Course_Option__r.TREX1__End_Date__c');
      $query->addField('TREX1__Course_Option__r.TREX1__End_Time__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Day_of_Week__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Instructor__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Location__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Location__r.id');
      $query->addField('TREX1__Course_Option__r.TREX1__Location__r.name');
      $query->addField('TREX1__Course_Option__r.TREX1__Age_Max__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Age_Min__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Register_Online_From_Date__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Register_Online_From_Time__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Register_Online_To_Time__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Registration_Total__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Total_Capacity_Available__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Type__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Unlimited_Capacity__c');
      $query->addField('TREX1__Course_Session__r.id');
      $query->addField('TREX1__Course_Session__r.TREX1__Description__c');
      $query->addField('TREX1__Course_Session__r.TREX1__Rich_Description__c');
      $query->addField('TREX1__Course_Session__r.TREX1__Course__r.name');
      $query->addField('TREX1__Course_Session__r.TREX1__Course__r.id');
      $query->addField('TREX1__Course_Session__r.TREX1__Course__r.TREX1__Description__c');
      $query->addField('TREX1__Course_Session__r.TREX1__Course__r.TREX1__Rich_Description__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Product__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Product__r.id');
      $query->addField('TREX1__Course_Option__r.TREX1__Product__r.name');
      $query->addField('TREX1__Course_Option__r.TREX1__Product__r.TREX1__Price_Description__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Unlimited_Waitlist_Capacity__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Waitlist_Total__c');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Available_Online__c', 'true');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Day_of_Week__c', 'null', '!=');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c', 'YESTERDAY', '>');
      $query->addCondition('TREX1__Course_Option__r.TREX1__End_Date__c', 'TODAY', '>=');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Start_Date__c', 'null', '!=');
      $query->addCondition('TREX1__Course_Session__r.TREX1__Num_Option_Entitlements__c', '1', '<=');
      $query->addCondition('TREX1__Course_Session__r.TREX1__Available_Online__c', 'true');

      if (!empty($locations)) {
        $locations = array_map(function ($location_id) {
          $location_id = '\'' . $location_id . '\'';
          return $location_id;
        }, $locations);
        $query->addCondition('TREX1__Course_Option__r.TREX1__Location__c', '(' . implode(',', $locations) . ')', 'IN');
      }

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of course options", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMemberships(string $location = NULL): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Membership_Type__c');
      $query->addField('TREX1__Membership_Type__c.id');
      $query->addField('TREX1__Membership_Type__c.name');
      $query->addField('TREX1__Membership_Type__c.TREX1__Description__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Available_For_Purchase__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Available_Online__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Cancellation_Fee__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Cancellation_Policy__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Freeze_Monthly_Fee__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Category__r.id');
      $query->addField('TREX1__Membership_Type__c.TREX1__Category__r.name');
      $query->addField('TREX1__Membership_Type__c.TREX1__Category__r.TREX1__Category_Description__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Category__r.Membership_Category_URL__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Location__r.id');
      $query->addField('TREX1__Membership_Type__c.TREX1__Location__r.name');
      $query->addField('TREX1__Membership_Type__c.TREX1__Location__r.Location_URL_Parameter__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Product__r.id');
      $query->addField('TREX1__Membership_Type__c.TREX1__Product__r.name');
      $query->addField('TREX1__Membership_Type__c.TREX1__Product__r.TREX1__Price_Description__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_1_Min_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_1_Max_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_1_Max_Allowed__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_1_Name__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_2_Min_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_2_Max_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_2_Max_Allowed__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_2_Name__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_3_Min_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_3_Max_Age__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_3_Name__c');
      $query->addField('TREX1__Membership_Type__c.TREX1__Group_3_Max_Allowed__c');
      $query->addCondition('TREX1__Membership_Type__c.TREX1__Available_For_Purchase__c', 'true');
      $query->addCondition('TREX1__Membership_Type__c.TREX1__Category__r.TREX1__Available_Online__c', 'true');

      if ($location) {
        $query->addCondition('TREX1__Membership_Type__c.TREX1__Location__r.id', "'$location'");
      }

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load the list of memberships", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadTotalAvailable(array $locations = []): array {
    try {
      $query = new SelectQuery();
      $query->setTable('TREX1__Course_Session_Option__c');
      $query->addField('TREX1__Course_Option__r.id');
      $query->addField('TREX1__Course_Option__r.TREX1__Total_Capacity_Available__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Unlimited_Waitlist_Capacity__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Waitlist_Total__c');
      $query->addField('TREX1__Course_Option__r.TREX1__Unlimited_Capacity__c');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Available_Online__c', 'true');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Day_of_Week__c', 'null', '!=');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c', 'YESTERDAY', '>');
      $query->addCondition('TREX1__Course_Option__r.TREX1__End_Date__c', 'TODAY', '>=');
      $query->addCondition('TREX1__Course_Option__r.TREX1__Start_Date__c', 'null', '!=');
      $query->addCondition('TREX1__Course_Session__r.TREX1__Available_Online__c', 'true');

      if (!empty($locations)) {
        $locations = array_map(function ($location_id) {
          $location_id = '\'' . $location_id . '\'';
          return $location_id;
        }, $locations);
        $query->addCondition('TREX1__Course_Option__r.TREX1__Location__c', '(' . implode(',', $locations) . ')', 'IN');
      }

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    } catch (\Exception $e) {
      return $this->processException("Can't load results for the next page", $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadNextPage(string $nextUrl): array {
    try {
      $url = $this->tractionRecSettings->get('api_base_url');
      $result = $this->tractionRecClient->send('GET', $url . $nextUrl);
      return $this->simplify($result);
    }
    catch (InvalidResponseException | InvalidTokenException $e) {
      return $this->processException("Can't load results for the next page", $e);
    }
  }

  /**
   * Cleans up TractionRec extra prefixes and suffixes for easier usage.
   *
   * @param array $array
   *   Response result from Traction Rec.
   *
   * @return array
   *   Results with cleaned keys.
   */
  protected function simplify(array $array): array {
    $new_array = [];
    foreach ($array as $key => $value) {
      $new_key = str_replace(['TREX1__', '__c', '__r'], '', (string) $key);
      if ($new_key === 'attributes') {
        continue;
      }
      if (is_array($value)) {
        $new_array[$new_key] = $this->simplify($value);
      }
      else {
        $new_array[$new_key] = $value;
      }
    }
    return $new_array;
  }

  /**
   * Proceed request exception message.
   *
   * @param string $message
   *   The message provided by referred function.
   * @param \Drupal\openy_traction_rec\InvalidResponseException|\Drupal\openy_traction_rec\InvalidTokenException $exception
   *   The exception object provided by service client response.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  protected function processException(string $message, InvalidResponseException | InvalidTokenException $exception): void {
    $message = (string) new FormattableMarkup($message . ': %message', ['%message' => $exception->getMessage()]);
    $this->logger->error($message);
    throw new ($exception::class)($message);
  }

}
