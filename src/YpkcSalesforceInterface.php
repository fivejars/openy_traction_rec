<?php

namespace Drupal\ypkc_salesforce;

/**
 * Interface for TractionRec abstract layer.
 */
interface YpkcSalesforceInterface {

  /**
   * Loads all TractionRec locations.
   *
   * @return array
   *   The list of locations.
   */
  public function loadLocations(): array;

  /**
   * Load the list of all TractionRec courses.
   *
   * @return array
   *   The list of courses.
   */
  public function loadCourses(): array;

  /**
   * Load TractionRec Program Categories Tags.
   *
   * @return array
   *   The list of tags.
   */
  public function loadProgramCategoryTags(): array;

  /**
   * Loads the list of TractionRec course options.
   *
   * @return array
   *   The list of options.
   */
  public function loadCourseOptions(): array;

  /**
   * Loads results for the next page.
   *
   * @param string $nextUrl
   *   The URL of the next results page.
   *
   * @return array
   *   Loaded TractionRec results for the next page.
   */
  public function loadNextPage(string $nextUrl): array;

}
