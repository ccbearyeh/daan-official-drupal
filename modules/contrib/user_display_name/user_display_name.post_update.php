<?php

/**
 * @file
 * Post updates for the user_display_name module.
 */

/**
 * Implements hook_post_update_NAME().
 *
 * Update user display name field definition.
 */
function user_display_name_post_update_convert_field_definition(&$sandbox) {
  $entity_type_id = 'user';

  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $definition_update_manager */
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();

  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
  $entity_type = $definition_update_manager->getEntityType($entity_type_id);

  // Update the display_name field storage definition.
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
  foreach (user_display_name_entity_base_field_info($entity_type) as $field_name => $definition) {
    $field_storage_definitions[$field_name] = $definition;
  }

  // We need to be able to update the anonymous user which has an ID of 0 so
  // we prevent auto incrementing the ID when inserting the anonymous user
  // in the temporary table used for the data migration.
  //
  // @see \Drupal\user\UserStorage::doSaveFieldItems();
  // @see https://drupal.org/i/3222123
  $database = \Drupal::database();
  if ($database->databaseType() === 'mysql') {
    $sql_mode = $database->query("SELECT @@sql_mode;")->fetchField();
    $database->query("SET sql_mode = '$sql_mode,NO_AUTO_VALUE_ON_ZERO'");
  }

  // Update the entity type. This will migrate the existing data.
  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  // Reset the SQL mode if we've changed it.
  if (isset($sql_mode)) {
    $database->query("SET sql_mode = '$sql_mode'");
  }

  // Retrieve the total number of user entities to update.
  if (!isset($sandbox['total'])) {
    $sandbox['total'] = \Drupal::database()
      ->select('users', 'u')
      ->countQuery()
      ->execute()
      ?->fetchField() ?? 0;
  }

  return t('@progress/@total user entities updated', [
    '@progress' => $sandbox['progress'] ?? 0,
    '@total' => $sandbox['total'],
  ]);
}
