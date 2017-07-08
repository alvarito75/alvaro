<?php

namespace Drupal\cocoven_properties\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\property\Entity\Property;
use Drupal\property\Entity\Land;
use Drupal\property\Entity\HouseChalet;
use Drupal\property\Entity\Office;
use Drupal\property\Entity\Room;
use Drupal\property\Entity\Building;
use Drupal\property\Entity\BusinessOffice;
use Drupal\property\Entity\Departament;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Drupal\user\Entity;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;

/**
 * Class CocovenPropertiesController.
 *
 * @package Drupal\cocoven_properties\Controller
 */
class CocovenPropertiesController extends ControllerBase {
  /*
   * Create local variable
   *
   */
  private $cocoven_login;
  protected $flood;
  protected $userStorage;
  protected $csrfToken;
  protected $userAuth;
  protected $routeProvider;
  protected $serializer;
  protected $serializerFormats = [];

  /*
   *  Instantiate my service
   *
   */
  public function __construct(Serializer $serializer, array $serializer_formats) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
  }

  /*
   *  Override the ControllerBase's create method
   *  When your controller needs to access services from the container
   *
   */
  public static function create(ContainerInterface $container) {
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $encoders = [new JsonEncoder()];
      $serializer = new Serializer([], $encoders);
    }

    return new static(
      $serializer, 
      $formats
    );
  }

  /**
   *
   * 
   * Create a property
   */
  public function createProperty(Request $request) {
    $content = $request->getContent();
    if (strpos($request->headers->get('Content-Type'), 'multipart/form-data;') !== 0) {
      $res = new JsonResponse();
      $res->setStatusCode(400, 'must submit multipart/form-data');
      return $res;
    }

    // Check if user is blocked
    $user = User::load($_POST['user']['id']);
    $userName = $user->get('name')->value;
    //$blocked = 
    
    if (!$this->userIsBlocked($userName)) {
      //create an entity
      $prueba = array();
      $mainPictureId = array();
      for ($i=0; $i < count($_FILES['property']['name']['picture']); $i++) { 
        $data = file_get_contents($_FILES['property']['tmp_name']['picture'][$i]);
        $file = file_save_data($data, 'public://alvaro/'.$_FILES['property']['name']['picture'][$i], FILE_EXISTS_RENAME);
        $prueba [$i] = array(
          'target_id' => $file->id(),
          'alt' => $_POST['property']['description'],
          'title' => $_POST['property']['description'],
        );
        $mainPictureId[$i] = $file->id();
      }

      // uploaded images to save
      $files = array();
      for ($i=0; $i < count($_FILES['property']['name']['picture']); $i++) { 
        $files[$i] = array(
          'name' => $_FILES['property']['name']['picture'][$i],
          'type' => $_FILES['property']['type']['picture'][$i],
          'tmp_name' => $_FILES['property']['tmp_name']['picture'][$i],
          'error' => $_FILES['property']['error']['picture'][$i],
          'size' => $_FILES['property']['size']['picture'][$i],
        );
      }

      // First property
      $values =  array(
        'name' => 'property - '. $_POST['property']['type'],
        'type_property' => $_POST['property']['type'],
        'transaction_type' => $_POST['property']['transactionType'],
        'latitud' => $_POST['property']['lat'],
        'longitud' => $_POST['property']['lng'],
        'locality' => $_POST['property']['locality'],
        'address' => $_POST['property']['address'],
        'city' => $_POST['property']['city'],
        'price' => $_POST['property']['price'],
        'currency' => $_POST['property']['currency'],
        'description' => $_POST['property']['description'],
        'surface' => $_POST['property']['surface'],
        'attendance' => $_POST['property']['attendance'],
        'main_picture' => $mainPictureId[0],
        'field_images_property' => $prueba,
        'user_id' => $_POST['user']['id']
      );
      $property = Property::create($values);
      $property->Save();
      $id = $property->id();
    
    
      /*$values_other = array(
        'name' => $_POST['property']['type']." - ". $id,
        'property_id' => $id,
        'bedroom' => $_POST['house']['bedroom'],
        'bathroom' => $_POST['house']['bathroom'],
        'user_id' => $_POST['user']['id'],
        'field_features_house_chalet' => $keyFeaturesHouse
      );
      $other = HouseChalet::create($values_other);
      $other->Save();*/

      // An asset
      switch ($_POST['property']['type']) {
        case 'house':
          //$values_other = $_POST['property']['type'];

          // Get features
          $featuresHouseSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_house_chalet.field_key_house_chalet_value as keyHouse
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_house_chalet
            ON taxonomy_term_data.tid=taxonomy_term__field_key_house_chalet.entity_id
            WHERE taxonomy_term_data.vid='features_house_chalet'
          ");
          
          foreach ($featuresHouseSql as $key => $value) {
            $featuresHouse[$value->id] = $value->keyHouse;
          }
          $keyFeaturesHouse = array();
          for ($x=0; $x < count($_POST['house']['features']); $x++) { 
            $keyFeaturesHouse[] = array_search($_POST['house']['features'][$x], $featuresHouse);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'bedroom' => $_POST['house']['bedroom'],
            'bathroom' => $_POST['house']['bathroom'],
            'user_id' => $_POST['user']['id'],
            'field_features_house_chalet' => $keyFeaturesHouse
          );
          $other = HouseChalet::create($values_other);
          $other->Save();
          break;
        
        case 'department':
          //$values_other = $_POST['type'];
          // Get features
          $featuresDepartmentSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_department.field_key_department_value as keyDepartment
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_department
            ON taxonomy_term_data.tid=taxonomy_term__field_key_department.entity_id
            WHERE taxonomy_term_data.vid='features_department'
          ");
          
          foreach ($featuresDepartmentSql as $key => $value) {
            $featuresDepartment[$value->id] = $value->keyDepartment;
          }
          $keyFeaturesDepartment = array();
          for ($x=0; $x < count($_POST['department']['features']); $x++) { 
            $keyFeaturesDepartment[] = array_search($_POST['department']['features'][$x], $featuresDepartment);
          }
          
          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'bedroom' => $_POST['department']['bedroom'],
            'bathroom' => $_POST['department']['bathroom'],
            'type_department' => $_POST['department']['type'],
            'user_id' => $_POST['user']['id'],
            'field_features_department' => $keyFeaturesDepartment
          );
          $other = Departament::create($values_other);
          $other->Save();
          break;

        case 'land':
          //$values_other = $_POST['type'];
          // Get features
          $featuresLandSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_land.field_key_land_value as keyLand
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_land
            ON taxonomy_term_data.tid=taxonomy_term__field_key_land.entity_id
            WHERE taxonomy_term_data.vid='features_land'
          ");
          
          foreach ($featuresLandSql as $key => $value) {
            $featuresLand[$value->id] = $value->keyLand;
          }
          $keyFeaturesLand = array();
          for ($x=0; $x < count($_POST['land']['features']); $x++) { 
            $keyFeaturesLand[] = array_search($_POST['land']['features'][$x], $featuresLand);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'field_features_land' => $keyFeaturesLand,
            'user_id' => $_POST['user']['id']
          );
          $other = Land::create($values_other);
          $other->Save();
          break;

        case 'office':
          //$values_other = $_POST['type'];
          // Get features
          $featuresOfficeSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_office.field_key_office_value as keyOffice
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_office
            ON taxonomy_term_data.tid=taxonomy_term__field_key_office.entity_id
            WHERE taxonomy_term_data.vid='features_office'
          ");
          
          foreach ($featuresOfficeSql as $key => $value) {
            $featuresOffice[$value->id] = $value->keyOffice;
          }
          $keyFeaturesOffice = array();
          for ($x=0; $x < count($_POST['office']['features']); $x++) { 
            $keyFeaturesOffice[] = array_search($_POST['office']['features'][$x], $featuresOffice);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'ambiance' => $_POST['office']['ambiance'],
            'bathroom' => $_POST['office']['bathroom'],
            'field_features_office' => $keyFeaturesOffice,
            'user_id' => $_POST['user']['id']
          );
          $other = Office::create($values_other);
          $other->Save();
          break;

        case 'business_office':
          //$values_other = $_POST['type'];
          // Get features
          $featuresBusinessOfficeSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_business_office.field_key_business_office_value as keyBusinessOffice
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_business_office
            ON taxonomy_term_data.tid=taxonomy_term__field_key_business_office.entity_id
            WHERE taxonomy_term_data.vid='features_business_office'
          ");
          
          foreach ($featuresBusinessOfficeSql as $key => $value) {
            $featuresBusinessOffice[$value->id] = $value->keyBusinessOffice;
          }
          $keyFeaturesBusinessOffice = array();
          for ($x=0; $x < count($_POST['business_office']['features']); $x++) { 
            $keyFeaturesBusinessOffice[] = array_search($_POST['business_office']['features'][$x], $featuresBusinessOffice);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'bathroom' => $_POST['business_office']['bathroom'],
            'field_features_business_office' => $keyFeaturesBusinessOffice,
            'user_id' => $_POST['user']['id']
          );
          $other = BusinessOffice::create($values_other);
          $other->Save();
          break;

        case 'room':
          //$values_other = $_POST['type'];
          // Get features
          $featuresRoomSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_room.field_key_room_value as keyRoom
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_room
            ON taxonomy_term_data.tid=taxonomy_term__field_key_room.entity_id
            WHERE taxonomy_term_data.vid='features_room'
          ");
          
          foreach ($featuresRoomSql as $key => $value) {
            $featuresRoom[$value->id] = $value->keyRoom;
          }
          $keyFeaturesRoom = array();
          for ($x=0; $x < count($_POST['room']['features']); $x++) { 
            $keyFeaturesRoom[] = array_search($_POST['room']['features'][$x], $featuresRoom);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'bathroom' => $_POST['room']['bathroom'],
            'field_features_room' => $keyFeaturesRoom,
            'user_id' => $_POST['user']['id']
          );
          $other = Room::create($values_other);
          $other->Save();
          break;

        case 'building':
          //$values_other = $_POST['type'];
          // Get features
          $featuresBuildingSql = db_query("
            SELECT taxonomy_term_data.tid as id, taxonomy_term__field_key_building.field_key_building_value as keyBuilding
            FROM taxonomy_term_data
            INNER JOIN taxonomy_term__field_key_building
            ON taxonomy_term_data.tid=taxonomy_term__field_key_building.entity_id
            WHERE taxonomy_term_data.vid='features_building'
          ");
          
          foreach ($featuresBuildingSql as $key => $value) {
            $featuresBuilding[$value->id] = $value->keyBuilding;
          }
          $keyFeaturesBuilding = array();
          for ($x=0; $x < count($_POST['building']['features']); $x++) { 
            $keyFeaturesBuilding[] = array_search($_POST['building']['features'][$x], $featuresBuilding);
          }

          $values_other = array(
            'name' => $_POST['property']['type']." - ". $id,
            'property_id' => $id,
            'floors' => $_POST['building']['floors'],
            'field_features_building' => $keyFeaturesBuilding,
            'user_id' => $_POST['user']['id']
          );
          $other = Building::create($values_other);
          $other->Save();
          break;
      }

      return new JsonResponse(array( 
        'status' => 'OK',
        'idProperty' => $id
      ));
    } else{
      return new JsonResponse(array( 
        'status' => 'BLOCKED_USER'
      ));
    }

  }

  /**
   *
   * Property detail
   */
  public function propertyDetail(Request $request){
    $format = $this->getRequestFormat($request);

    $content = $request->getContent();
    $params = json_decode($content);
    $credentials = $this->serializer->decode($content, $format);

    if (!isset($credentials['idProperty']) && !isset($credentials['idUser'])) {
      throw new BadRequestHttpException('Missing credentials.');
    }
    // Verify if exists these parameters
    if (!isset($credentials['idProperty'])) {
      throw new BadRequestHttpException('Missing credentials: idProperty.');
    }
    if (!isset($credentials['idUser'])) {
      throw new BadRequestHttpException('Missing credentials. idUser.');
    }

    if ($credentials['idUser'] != '') {
      // Get the fully property
      $propertySql = db_query("
        SELECT property_field_data.id as id_property, property_field_data.status, property_field_data.user_id, property_field_data.type_property, property_field_data.transaction_type, property_field_data.locality, property_field_data.address, property_field_data.city, property_field_data.surface, property_field_data.surfaceTypeMeasure, property_field_data.price, property_field_data.currency, property_field_data.latitud, property_field_data.longitud, property_field_data.attendance, property_field_data.description, file_managed.uri, GROUP_CONCAT(favorite_field_data.board_id SEPARATOR ',') as boards_ids
        FROM property_field_data
        INNER JOIN file_managed
        ON (property_field_data.main_picture=file_managed.fid)
        LEFT JOIN favorite_field_data
        ON (property_field_data.id=favorite_field_data.property_id and favorite_field_data.user_id=".$credentials['idUser'].")
        LEFT JOIN board_shared_field_data
        ON (favorite_field_data.board_id=board_shared_field_data.board_id and board_shared_field_data.client_id=".$credentials['idUser'].")
        WHERE property_field_data.id=".$credentials['idProperty']."
        GROUP BY id_property, property_field_data.status, property_field_data.user_id, property_field_data.type_property, property_field_data.transaction_type, property_field_data.locality, property_field_data.address, property_field_data.city, property_field_data.surface, property_field_data.surfaceTypeMeasure, property_field_data.price, property_field_data.currency, property_field_data.latitud, property_field_data.longitud, property_field_data.attendance, property_field_data.description, file_managed.uri
      ");
      $result = array();
      foreach ($propertySql as $key => $value) {
        $favorite_property_id = !is_null($value->boards_ids)? $value->boards_ids : "";
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property'] = array(
          'idProperty' => $value->id_property,
          'type' => $value->type_property,
          'transactionType' => $value->transaction_type,
          'locality' => $value->locality,
          'address' => $value->address,
          'city' => $value->city,
          'surface' => $value->surface,
          'surfaceTypeMeasure' => $value->surfaceTypeMeasure,
          'price' => $value->price,
          'currency' => $value->currency,
          'favorite' => $favorite_property_id,
          'picture' => $path,
          'lat' => $value->latitud,
          'lng' => $value->longitud,
          'attendance' => $value->attendance,
          'description' => $value->description,
          'owner' => $value->user_id
        );
        $isPublished = $value->status;
        $result['status'] = $isPublished;
      }
      // Get asset according its type
      switch ($result['property']['type']) {
        case 'house':
          // Get features
          $propertyFeatures = db_query("
            SELECT house_chalet_field_data.bedroom, house_chalet_field_data.bathroom, GROUP_CONCAT(house_chalet__field_features_house_chalet.field_features_house_chalet_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_house_chalet.field_key_house_chalet_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM house_chalet_field_data
            LEFT JOIN house_chalet__field_features_house_chalet
            ON house_chalet_field_data.id=house_chalet__field_features_house_chalet.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON house_chalet__field_features_house_chalet.field_features_house_chalet_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_house_chalet
            ON (house_chalet__field_features_house_chalet.field_features_house_chalet_target_id=taxonomy_term__field_key_house_chalet.entity_id)
            WHERE house_chalet_field_data.property_id=".$credentials['idProperty']."
            GROUP BY house_chalet_field_data.bedroom, house_chalet_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bedroom' => $value->bedroom,
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;
        
        case 'department':
          // Get features
          $propertyFeatures = db_query("
            SELECT departament_field_data.bedroom, departament_field_data.bathroom, departament_field_data.type_department, GROUP_CONCAT(departament__field_features_department.field_features_department_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_department.field_key_department_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM departament_field_data
            LEFT JOIN departament__field_features_department
            ON departament_field_data.id=departament__field_features_department.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON departament__field_features_department.field_features_department_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_department
            ON (departament__field_features_department.field_features_department_target_id=taxonomy_term__field_key_department.entity_id)
            WHERE departament_field_data.property_id=".$credentials['idProperty']."
            GROUP BY departament_field_data.bedroom, departament_field_data.bathroom, departament_field_data.type_department
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bedroom' => $value->bedroom,
              'bathroom' => $value->bathroom,
              'type' => $value->type_department
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'room':
          // Get features
          $propertyFeatures = db_query("
            SELECT room_field_data.bathroom, GROUP_CONCAT(room__field_features_room.field_features_room_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_room.field_key_room_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM room_field_data
            LEFT JOIN room__field_features_room
            ON room_field_data.id=room__field_features_room.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON room__field_features_room.field_features_room_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_room
            ON (room__field_features_room.field_features_room_target_id=taxonomy_term__field_key_room.entity_id)
            WHERE room_field_data.property_id=".$credentials['idProperty']."
            GROUP BY room_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'office':
          // Get features
          $propertyFeatures = db_query("
            SELECT office_field_data.bathroom, office_field_data.ambiance, GROUP_CONCAT(office__field_features_office.field_features_office_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_office.field_key_office_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM office_field_data
            LEFT JOIN office__field_features_office
            ON office_field_data.id=office__field_features_office.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON office__field_features_office.field_features_office_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_office
            ON (office__field_features_office.field_features_office_target_id=taxonomy_term__field_key_office.entity_id)
            WHERE office_field_data.property_id=".$credentials['idProperty']."
            GROUP BY office_field_data.bathroom, office_field_data.ambiance
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom,
              'ambiance' => $value->ambiance
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'business_office':
          // Get features
          $propertyFeatures = db_query("
            SELECT business_office_field_data.bathroom, GROUP_CONCAT(business_office__field_features_business_office.field_features_business_office_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_business_office.field_key_business_office_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM business_office_field_data
            LEFT JOIN business_office__field_features_business_office
            ON business_office_field_data.id=business_office__field_features_business_office.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON business_office__field_features_business_office.field_features_business_office_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_business_office
            ON (business_office__field_features_business_office.field_features_business_office_target_id=taxonomy_term__field_key_business_office.entity_id)
            WHERE business_office_field_data.property_id=".$credentials['idProperty']."
            GROUP BY business_office_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'land':
          // Get features
          $propertyFeatures = db_query("
            SELECT GROUP_CONCAT(land__field_features_land.field_features_land_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_land.field_key_land_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM land_field_data
            LEFT JOIN land__field_features_land
            ON land_field_data.id=land__field_features_land.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON land__field_features_land.field_features_land_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_land
            ON (land__field_features_land.field_features_land_target_id=taxonomy_term__field_key_land.entity_id)
            WHERE land_field_data.property_id=".$credentials['idProperty']."
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'building':
          // Get features
          $propertyFeatures = db_query("
            SELECT building_field_data.floors, GROUP_CONCAT(building__field_features_building.field_features_building_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_building.field_key_building_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM building_field_data
            LEFT JOIN building__field_features_building
            ON building_field_data.id=building__field_features_building.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON building__field_features_building.field_features_building_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_building
            ON (building__field_features_building.field_features_building_target_id=taxonomy_term__field_key_building.entity_id)
            WHERE building_field_data.property_id=".$credentials['idProperty']."
            GROUP BY building_field_data.floors
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'floors' => $value->floors
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;
      }

      // Get the owner
      $owner = db_query("
        SELECT users_field_data.uid, user__field_name.field_name_value, user__field_last_name.field_last_name_value, user__field_telefono_celular.field_telefono_celular_value, user__roles.roles_target_id, file_managed.uri
        FROM users_field_data

        LEFT JOIN user__field_last_name
        ON users_field_data.uid=user__field_last_name.entity_id

        LEFT JOIN user__field_name
        ON users_field_data.uid=user__field_name.entity_id
        
        LEFT JOIN user__field_telefono_celular
        ON users_field_data.uid=user__field_telefono_celular.entity_id

        LEFT JOIN user__roles
        ON users_field_data.uid=user__roles.entity_id

        LEFT JOIN user__user_picture
        ON users_field_data.uid=user__user_picture.entity_id

        LEFT JOIN file_managed
        ON user__user_picture.user_picture_target_id=file_managed.fid

        WHERE users_field_data.uid=".$result['property']['owner']."
      ");
      foreach ($owner as $key => $value) {
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property']['owner'] = array(
          'name' => $value->field_name_value,
          'lastName' => $value->field_last_name_value,
          'phone' => $value->field_telefono_celular_value,
          'role' => $value->roles_target_id,
          'picture' => $path,
        );
      }
      // Get the pictures
      $pictures = db_query("
        SELECT property_field_data.id, property__field_images_property.field_images_property_target_id, file_managed.uri
        FROM  property_field_data

        LEFT JOIN property__field_images_property
        ON property_field_data.id=property__field_images_property.entity_id

        LEFT JOIN file_managed
        ON property__field_images_property.field_images_property_target_id=file_managed.fid

        WHERE property_field_data.id=".$credentials['idProperty']."
      ");
      
      foreach ($pictures as $key => $value) {
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property']['pictures'][] =$path;
      }

      $res_properties = $result;
    } else{
      // Get the fully property
      $propertySql = db_query("
        SELECT property_field_data.id as id_property,  property_field_data.status, property_field_data.user_id, property_field_data.type_property, property_field_data.transaction_type, property_field_data.locality, property_field_data.address, property_field_data.city, property_field_data.surface, property_field_data.surfaceTypeMeasure, property_field_data.price, property_field_data.currency, property_field_data.latitud, property_field_data.longitud, property_field_data.attendance, property_field_data.description, file_managed.uri, '' as boards_ids
        FROM property_field_data
        INNER JOIN file_managed
        ON (property_field_data.main_picture=file_managed.fid)
        WHERE property_field_data.id=".$credentials['idProperty']."
        GROUP BY id_property,  property_field_data.status, property_field_data.user_id, property_field_data.type_property, property_field_data.transaction_type, property_field_data.locality, property_field_data.address, property_field_data.city, property_field_data.surface, property_field_data.surfaceTypeMeasure, property_field_data.price, property_field_data.currency, property_field_data.latitud, property_field_data.longitud, property_field_data.attendance, property_field_data.description, file_managed.uri
      ");
      $result = array();
      foreach ($propertySql as $key => $value) {
        $favorite_property_id = !is_null($value->boards_ids)? $value->boards_ids : "";
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property'] = array(
          'idProperty' => $value->id_property,
          'type' => $value->type_property,
          'transactionType' => $value->transaction_type,
          'locality' => $value->locality,
          'address' => $value->address,
          'city' => $value->city,
          'surface' => $value->surface,
          'surfaceTypeMeasure' => $value->surfaceTypeMeasure,
          'price' => $value->price,
          'currency' => $value->currency,
          'favorite' => $favorite_property_id,
          'picture' => $path,
          'lat' => $value->latitud,
          'lng' => $value->longitud,
          'attendance' => $value->attendance,
          'description' => $value->description,
          'owner' => $value->user_id
        );
        $isPublished = $value->status;
        $result['status'] = $isPublished;
      }
      // Get asset according its type
      switch ($result['property']['type']) {
        case 'house':
          // Get features
          $propertyFeatures = db_query("
            SELECT house_chalet_field_data.bedroom, house_chalet_field_data.bathroom, GROUP_CONCAT(house_chalet__field_features_house_chalet.field_features_house_chalet_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_house_chalet.field_key_house_chalet_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM house_chalet_field_data
            LEFT JOIN house_chalet__field_features_house_chalet
            ON house_chalet_field_data.id=house_chalet__field_features_house_chalet.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON house_chalet__field_features_house_chalet.field_features_house_chalet_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_house_chalet
            ON (house_chalet__field_features_house_chalet.field_features_house_chalet_target_id=taxonomy_term__field_key_house_chalet.entity_id)
            WHERE house_chalet_field_data.property_id=".$credentials['idProperty']."
            GROUP BY house_chalet_field_data.bedroom, house_chalet_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bedroom' => $value->bedroom,
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;
        
        case 'department':
          // Get features
          $propertyFeatures = db_query("
            SELECT departament_field_data.bedroom, departament_field_data.bathroom, departament_field_data.type_department, GROUP_CONCAT(departament__field_features_department.field_features_department_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_department.field_key_department_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM departament_field_data
            LEFT JOIN departament__field_features_department
            ON departament_field_data.id=departament__field_features_department.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON departament__field_features_department.field_features_department_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_department
            ON (departament__field_features_department.field_features_department_target_id=taxonomy_term__field_key_department.entity_id)
            WHERE departament_field_data.property_id=".$credentials['idProperty']."
            GROUP BY departament_field_data.bedroom, departament_field_data.bathroom, departament_field_data.type_department
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bedroom' => $value->bedroom,
              'bathroom' => $value->bathroom,
              'type' => $value->type_department
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'room':
          // Get features
          $propertyFeatures = db_query("
            SELECT room_field_data.bathroom, GROUP_CONCAT(room__field_features_room.field_features_room_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_room.field_key_room_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM room_field_data
            LEFT JOIN room__field_features_room
            ON room_field_data.id=room__field_features_room.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON room__field_features_room.field_features_room_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_room
            ON (room__field_features_room.field_features_room_target_id=taxonomy_term__field_key_room.entity_id)
            WHERE room_field_data.property_id=".$credentials['idProperty']."
            GROUP BY room_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'office':
          // Get features
          $propertyFeatures = db_query("
            SELECT office_field_data.bathroom, office_field_data.ambiance, GROUP_CONCAT(office__field_features_office.field_features_office_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_office.field_key_office_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM office_field_data
            LEFT JOIN office__field_features_office
            ON office_field_data.id=office__field_features_office.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON office__field_features_office.field_features_office_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_office
            ON (office__field_features_office.field_features_office_target_id=taxonomy_term__field_key_office.entity_id)
            WHERE office_field_data.property_id=".$credentials['idProperty']."
            GROUP BY office_field_data.bathroom, office_field_data.ambiance
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom,
              'ambiance' => $value->ambiance
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'business_office':
          // Get features
          $propertyFeatures = db_query("
            SELECT business_office_field_data.bathroom, GROUP_CONCAT(business_office__field_features_business_office.field_features_business_office_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_business_office.field_key_business_office_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM business_office_field_data
            LEFT JOIN business_office__field_features_business_office
            ON business_office_field_data.id=business_office__field_features_business_office.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON business_office__field_features_business_office.field_features_business_office_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_business_office
            ON (business_office__field_features_business_office.field_features_business_office_target_id=taxonomy_term__field_key_business_office.entity_id)
            WHERE business_office_field_data.property_id=".$credentials['idProperty']."
            GROUP BY business_office_field_data.bathroom
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'bathroom' => $value->bathroom
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'land':
          // Get features
          $propertyFeatures = db_query("
            SELECT GROUP_CONCAT(land__field_features_land.field_features_land_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_land.field_key_land_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM land_field_data
            LEFT JOIN land__field_features_land
            ON land_field_data.id=land__field_features_land.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON land__field_features_land.field_features_land_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_land
            ON (land__field_features_land.field_features_land_target_id=taxonomy_term__field_key_land.entity_id)
            WHERE land_field_data.property_id=".$credentials['idProperty']."
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;

        case 'building':
          // Get features
          $propertyFeatures = db_query("
            SELECT building_field_data.floors, GROUP_CONCAT(building__field_features_building.field_features_building_target_id SEPARATOR ',') as features_ids, GROUP_CONCAT(taxonomy_term__field_key_building.field_key_building_value SEPARATOR ',') as features_key, GROUP_CONCAT(taxonomy_term_field_data.name SEPARATOR ',') as features_name
            FROM building_field_data
            LEFT JOIN building__field_features_building
            ON building_field_data.id=building__field_features_building.entity_id
            LEFT JOIN taxonomy_term_field_data
            ON building__field_features_building.field_features_building_target_id=taxonomy_term_field_data.tid
            LEFT JOIN taxonomy_term__field_key_building
            ON (building__field_features_building.field_features_building_target_id=taxonomy_term__field_key_building.entity_id)
            WHERE building_field_data.property_id=".$credentials['idProperty']."
            GROUP BY building_field_data.floors
          ");
          $resultType = array();
          $resFeatures = array();
          foreach ($propertyFeatures as $key => $value) {
            $resultType = array(
              'floors' => $value->floors
            );
            $resFeatures['features_key'] = $value->features_key;
            $resFeatures['features_name'] = $value->features_name;
          }
          
          // Explode the features array
          $features_key = explode(",", $resFeatures['features_key']);
          $features_name = explode(",", $resFeatures['features_name']);
          $resultFeatures = array();
          for ($i=0; $i < count($features_key); $i++) { 
            $resultFeatures[] = array(
              'code' => $features_key[$i],
              'label' => $features_name[$i],
            ); 
          }
          $result['property']['features'] = $resultFeatures;

          $result['property'][$result['property']['type']] = $resultType;
          break;
      }

      // Get the owner
      $owner = db_query("
        SELECT users_field_data.uid, user__field_name.field_name_value, user__field_last_name.field_last_name_value, user__field_telefono_celular.field_telefono_celular_value, user__roles.roles_target_id, file_managed.uri
        FROM users_field_data

        LEFT JOIN user__field_last_name
        ON users_field_data.uid=user__field_last_name.entity_id

        LEFT JOIN user__field_name
        ON users_field_data.uid=user__field_name.entity_id
        
        LEFT JOIN user__field_telefono_celular
        ON users_field_data.uid=user__field_telefono_celular.entity_id

        LEFT JOIN user__roles
        ON users_field_data.uid=user__roles.entity_id

        LEFT JOIN user__user_picture
        ON users_field_data.uid=user__user_picture.entity_id

        LEFT JOIN file_managed
        ON user__user_picture.user_picture_target_id=file_managed.fid

        WHERE users_field_data.uid=".$result['property']['owner']."
      ");
      foreach ($owner as $key => $value) {
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property']['owner'] = array(
          'name' => $value->field_name_value,
          'lastName' => $value->field_last_name_value,
          'phone' => $value->field_telefono_celular_value,
          'role' => $value->roles_target_id,
          'picture' => $path,
        );
      }
      // Get the pictures
      $pictures = db_query("
        SELECT property_field_data.id, property__field_images_property.field_images_property_target_id, file_managed.uri
        FROM  property_field_data

        LEFT JOIN property__field_images_property
        ON property_field_data.id=property__field_images_property.entity_id

        LEFT JOIN file_managed
        ON property__field_images_property.field_images_property_target_id=file_managed.fid

        WHERE property_field_data.id=".$credentials['idProperty']."
      ");
      
      foreach ($pictures as $key => $value) {
        $path = str_replace("public://","/sites/default/files/", $value->uri);
        $result['property']['pictures'][] =$path;
      }

      $res_properties = $result;
    }

    return new JsonResponse($res_properties);
  }

  /**
   *
   * Set property unpublished
   */
  public function unpublishProperty(Request $request){
    $format = $this->getRequestFormat($request);

    $content = $request->getContent();
    $params = json_decode($content);
    $credentials = $this->serializer->decode($content, $format);

    if (!isset($credentials['idProperty']) && !isset($credentials['idUser']) && !isset($credentials['state'])) {
      throw new BadRequestHttpException('Missing credentials.');
    }
    // Verify if exists these parameters
    if (!isset($credentials['idProperty'])) {
      throw new BadRequestHttpException('Missing credentials: idProperty.');
    }
    if (!isset($credentials['idUser'])) {
      throw new BadRequestHttpException('Missing credentials. idUser.');
    }
    if (!isset($credentials['state'])) {
      throw new BadRequestHttpException('Missing credentials. state.');
    }

    // Verify user owns this property
    $property = Property::load($credentials['idProperty']);
    $propertyAuthor = $property->getOwnerId();
    if ($propertyAuthor == $credentials['idUser']) {
      if ($credentials['state'] == '0') {
        $property->set('status', FALSE);
        $property->save();
        $res = array(
          'status' => 'OK'
        );
      } else{
        // Verify quantity
        $userSql = db_query("
          SELECT COUNT(property_field_data.id) as total
          FROM property_field_data
          WHERE property_field_data.status=1 AND property_field_data.user_id=".$credentials['idUser']."
        ");
        foreach ($userSql as $key => $value) {
          $createdProperties = $value->total;
        }
        // Load user
        $user = user_load($credentials['idUser']);
        $role = $user->getRoles(TRUE);
        $restrictionSql = db_query("
          SELECT restriction_field_data.maxNumProperties
          FROM restriction_field_data
          WHERE restriction_field_data.name='".$role[0]."'
        ");
        $res = array();
        foreach ($restrictionSql as $key => $value) {
          $maxAllowed = $value->maxNumProperties;
        }

        if ((integer)$createdProperties < (integer)$maxAllowed) {
          $property->set('status', TRUE);
          $property->save();
          $res = array(
            'status' => 'OK',
          );
        } else{
          $res = array(
            'status' => 'PUBLISHED_LIMIT_REACHED',
            'maxAllowed' => $maxAllowed,
            'createdProperties' => $createdProperties
          );
        }
      }
    }
    return new JsonResponse($res);
  }

  /**
   * Gets the format of the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return string
   *   The format of the request.
   */
  protected function getRequestFormat(Request $request) {
    $format = $request->getRequestFormat();
    if (!in_array($format, $this->serializerFormats)) {
      throw new BadRequestHttpException("Unrecognized format: $format.");
    }
    return $format;
  }

  /**
   * Verifies if the user is blocked.
   *
   * @param string $name
   *   The username.
   *
   * @return bool
   *   TRUE if the user is blocked, otherwise FALSE.
   */
  protected function userIsBlocked($name) {
    return user_is_blocked($name);
  }

}
