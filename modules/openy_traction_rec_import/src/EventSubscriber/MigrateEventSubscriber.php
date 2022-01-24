<?php

namespace Drupal\openy_traction_rec_import\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Events subscriber for migrate events.
 */
class MigrateEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PRE_ROW_SAVE => 'onPreRowSave',
    ];
  }

  /**
   * PRE_ROW_SAVE event callback.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPreRowSave(MigratePreRowSaveEvent $event) {
    // We don't create paragraph in process plugin,
    // because it won't be attached to any parent if row is skipped.
    // So we creating it here when the row is ready for saving.
    $row = $event->getRow();
    $values = $row->getDestinationProperty('field_session_time');

    // Skip if paragraph has already created in plugin.
    if (!$values || isset($values['target_id'])) {
      return;
    }

    // Create new paragraph if data is passed instead of target_id.
    $paragraph = Paragraph::create($values);
    $paragraph->isNew();
    $paragraph->save();

    $values = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    $row->setDestinationProperty('field_session_time', $values);
  }

}
