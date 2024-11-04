<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Exception\GuzzleException;

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
  protected LoggerChannel $logger;

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
      $result = $this->tractionRecClient->executeQuery('SELECT
        TREX1__Location__c.id,
        TREX1__Location__c.name,
        TREX1__Location__c.TREX1__Address_City__c,
        TREX1__Location__c.TREX1__Address_Country__c,
        TREX1__Location__c.TREX1__Address_State__c,
        TREX1__Location__c.TREX1__Address_Street__c,
        TREX1__Location__c.TREX1__Address_Postal_Code__c
        FROM TREX1__Location__c');

      return $this->simplify($result);
    }
    catch (\Exception | GuzzleException $e) {
      $message = 'Can\'t load the list of locations: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCourses(): array {
    try {
      $result = $this->tractionRecClient->executeQuery('SELECT
        TREX1__Course__c.id,
        TREX1__Course__c.name,
        TREX1__Course__c.TREX1__Description__c,
        TREX1__Course__c.TREX1__Rich_Description__c,
        TREX1__Course__c.TREX1__Program__r.id,
        TREX1__Course__c.TREX1__Program__r.name,
        TREX1__Course__c.TREX1__Available__c
      FROM TREX1__Course__c WHERE TREX1__Available_Online__c = true');

      return $this->simplify($result);
    }
    catch (\Exception | GuzzleException $e) {
      $message = 'Can\'t load the list of classes: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadProgramCategoryTags(): array {
    try {
      $result = $this->tractionRecClient->executeQuery(
        'SELECT
        TREX1__Program_Category_Tag__c.id,
        TREX1__Program_Category_Tag__c.name,
        TREX1__Program_Category_Tag__c.TREX1__Program__r.id,
        TREX1__Program_Category_Tag__c.TREX1__Program__r.name,
        TREX1__Program_Category_Tag__c.TREX1__Program__r.TREX1__Available__c,
        TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.id,
        TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.name,
        TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.TREX1__Available__c
        FROM TREX1__Program_Category_Tag__c
        WHERE TREX1__Program__r.TREX1__Available_Online__c = true
          AND TREX1__Program_Category__r.TREX1__Available_Online__c = true'
      );
      return $this->simplify($result);
    }
    catch (\Exception | GuzzleException $e) {
      $message = 'Can\'t load the list of program category tags: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCourseOptions(array $locations = []): array {
    try {
      $query = 'SELECT
        TREX1__Course_Option__r.id,
        TREX1__Course_Option__r.name,
        TREX1__Course_Option__r.TREX1__Available_Online__c,
        TREX1__Course_Option__r.TREX1__Available__c,
        TREX1__Course_Option__r.TREX1__capacity__c,
        TREX1__Course_Option__r.TREX1__Start_Date__c,
        TREX1__Course_Option__r.TREX1__Start_Time__c,
        TREX1__Course_Option__r.TREX1__End_Date__c,
        TREX1__Course_Option__r.TREX1__End_Time__c,
        TREX1__Course_Option__r.TREX1__Day_of_Week__c,
        TREX1__Course_Option__r.TREX1__Instructor__c,
        TREX1__Course_Option__r.TREX1__Location__c,
        TREX1__Course_Option__r.TREX1__Location__r.id,
        TREX1__Course_Option__r.TREX1__Location__r.name,
        TREX1__Course_Option__r.TREX1__Age_Max__c,
        TREX1__Course_Option__r.TREX1__Age_Min__c,
        TREX1__Course_Option__r.TREX1__Register_Online_From_Date__c,
        TREX1__Course_Option__r.TREX1__Register_Online_From_Time__c,
        TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c,
        TREX1__Course_Option__r.TREX1__Register_Online_To_Time__c,
        TREX1__Course_Option__r.TREX1__Registration_Total__c,
        TREX1__Course_Option__r.TREX1__Total_Capacity_Available__c,
        TREX1__Course_Option__r.TREX1__Type__c,
        TREX1__Course_Option__r.TREX1__Unlimited_Capacity__c,
        TREX1__Course_Session__r.id,
        TREX1__Course_Session__r.TREX1__Course__r.name,
        TREX1__Course_Session__r.TREX1__Course__r.id,
        TREX1__Course_Session__r.TREX1__Course__r.TREX1__Description__c,
        TREX1__Course_Session__r.TREX1__Course__r.TREX1__Rich_Description__c,
        TREX1__Course_Option__r.TREX1__Product__c,
        TREX1__Course_Option__r.TREX1__Product__r.id,
        TREX1__Course_Option__r.TREX1__Product__r.name,
        TREX1__Course_Option__r.TREX1__Product__r.TREX1__Price_Description__c,
        TREX1__Course_Option__r.TREX1__Unlimited_Waitlist_Capacity__c,
        TREX1__Course_Option__r.TREX1__Waitlist_Total__c
      FROM TREX1__Course_Session_Option__c
      WHERE TREX1__Course_Option__r.TREX1__Available_Online__c = true
        AND TREX1__Course_Option__r.TREX1__Day_of_Week__c  != null
        AND TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c > YESTERDAY
        AND TREX1__Course_Option__r.TREX1__End_Date__c >= TODAY
        AND TREX1__Course_Option__r.TREX1__Start_Date__c != null
        AND TREX1__Course_Session__r.TREX1__Num_Option_Entitlements__c <= 1';

      if (!empty($locations)) {
        $locations = array_map(function ($location_id) {
          $location_id = '\'' . $location_id . '\'';
          return $location_id;
        }, $locations);
        $query .= ' AND TREX1__Course_Option__r.TREX1__Location__c IN (' . implode(',', $locations) . ')';
      }

      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (\Exception | GuzzleException $e) {
      $message = 'Can\'t load the list of course options: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMemberships(string $location = NULL): array {
    try {
      $query = 'SELECT
        TREX1__Membership_Type__c.id,
        TREX1__Membership_Type__c.name,
        TREX1__Membership_Type__c.TREX1__Description__c,
        TREX1__Membership_Type__c.TREX1__Available_For_Purchase__c,
        TREX1__Membership_Type__c.TREX1__Available_Online__c,
        TREX1__Membership_Type__c.TREX1__Cancellation_Fee__c,
        TREX1__Membership_Type__c.TREX1__Cancellation_Policy__c,
        TREX1__Membership_Type__c.TREX1__Freeze_Monthly_Fee__c,
        TREX1__Membership_Type__c.TREX1__Category__r.id,
        TREX1__Membership_Type__c.TREX1__Category__r.name,
        TREX1__Membership_Type__c.TREX1__Category__r.TREX1__Category_Description__c,
        TREX1__Membership_Type__c.TREX1__Category__r.Membership_Category_URL__c,
        TREX1__Membership_Type__c.TREX1__Location__r.id,
        TREX1__Membership_Type__c.TREX1__Location__r.name,
        TREX1__Membership_Type__c.TREX1__Location__r.Location_URL_Parameter__c,
        TREX1__Membership_Type__c.TREX1__Product__r.id,
        TREX1__Membership_Type__c.TREX1__Product__r.name,
        TREX1__Membership_Type__c.TREX1__Product__r.TREX1__Price_Description__c,
        TREX1__Membership_Type__c.TREX1__Group_1_Min_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_1_Max_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_1_Max_Allowed__c,
        TREX1__Membership_Type__c.TREX1__Group_1_Name__c,
        TREX1__Membership_Type__c.TREX1__Group_2_Min_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_2_Max_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_2_Max_Allowed__c,
        TREX1__Membership_Type__c.TREX1__Group_2_Name__c,
        TREX1__Membership_Type__c.TREX1__Group_3_Min_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_3_Max_Age__c,
        TREX1__Membership_Type__c.TREX1__Group_3_Name__c,
        TREX1__Membership_Type__c.TREX1__Group_3_Max_Allowed__c
        FROM TREX1__Membership_Type__c
        WHERE
              TREX1__Membership_Type__c.TREX1__Available_For_Purchase__c = true
          AND TREX1__Membership_Type__c.TREX1__Category__r.TREX1__Available_Online__c = true';

      if ($location) {
        $query .= " AND TREX1__Membership_Type__c.TREX1__Location__r.id = '$location'";
      }
      $result = $this->tractionRecClient->executeQuery($query);
      return $this->simplify($result);
    }
    catch (\Exception | GuzzleException $e) {
      $message = 'Can\'t load the list of program category tags: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
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
    catch (\Exception | GuzzleException | InvalidTokenException $e) {
      $message = 'Can\'t load results for the next page: ' . $e->getMessage();
      $this->logger->error($message);
      return [];
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
  private function simplify(array $array): array {
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

}
