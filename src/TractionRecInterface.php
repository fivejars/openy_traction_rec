<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

/**
 * Interface for TractionRec abstract layer.
 */
interface TractionRecInterface {

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
   * Load the list of all TractionRec course sessions.
   *
   * @return array
   *   The list of course sessions.
   */
  public function loadCourseSessions(): array;

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
   * @param arraystring $locations
   *   (Optional) Filters course options by TractionRec locations ID's.
   *
   * @return array
   *   The list of options.
   */
  public function loadCourseOptions(array $locations = []): array;

  /**
   * Loads membership types.
   *
   * @param string|null $location
   *   (Optional) TractionRec branch ID. Filters membership types by location.
   *
   * @return array
   *   The array of loaded membership types.
   */
  public function loadMemberships(string $location = NULL): array;

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
