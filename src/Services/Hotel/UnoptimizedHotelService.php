<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\PDOSingleton;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use App\Common\Timers;
use Exception;
use PDO;

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
    
  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('TimerGetBD');
    $PDO = PDOSingleton::get();
    $timer->endTimer('TimerGetBD', $timerId);
    return $PDO;
  }
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('TimerGetMetas');

    $keys = ['address_1','address_2','address_city','address_zip','address_country','geo_lat','geo_lng','coverImage','phone'];

    $metaDatas = [
      'address' => [
        'address_1',
        'address_2',
        'address_city',
        'address_zip',
        'address_country',
      ],
      'geo_lat',
      'geo_lng',
      'coverImage',
      'phone',
    ];

    $userId = $hotel->getId();
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT meta_value FROM wp_usermeta WHERE user_id = :userid AND meta_key = :key" );
    $stmt->bindParam('userid',$userId,PDO::PARAM_INT);

    foreach($keys as $key){
      if($key == 'address_1'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['address']['address_1'] = $meta["meta_value"];
      }
      if($key == 'address_2'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['address']['address_2'] = $meta["meta_value"];
      }
      if($key == 'address_city'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['address']['address_city'] = $meta["meta_value"];
      }
      if($key == 'address_zip'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['address']['address_zip'] = $meta["meta_value"];
      }
      if($key == 'address_country'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['address']['address_country'] = $meta["meta_value"];
      }
      if($key == 'geo_lat'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);  
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['geo_lat'] = $meta["meta_value"];
      }
      if($key == 'geo_lng'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['geo_lng'] = $meta["meta_value"];
      }
      if($key == 'coverImage'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['coverImage'] = $meta["meta_value"];
      }
      if($key == 'phone'){
        $stmt->bindParam('key',$key,PDO::PARAM_STR);
        $stmt->execute();
        $meta = $stmt->fetch( PDO::FETCH_ASSOC );
        $metaDatas['phone'] = $meta["meta_value"];
      }
    }

    $timer->endTimer('TimerGetMetas', $timerId);
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('TimerGetReview');
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( "SELECT ROUND(AVG(meta_value)) AS rating, COUNT(meta_value) AS count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetch( PDO::FETCH_ASSOC );
    $timer->endTimer('TimerGetReview', $timerId);
    return $reviews;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */

  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('TimerGetCheapestRoom');

    $whereClause = [];
    if ( isset( $args['surface']['min'] )  )
      $whereClause[] = 'surfaceData.meta_value >= ' . $args['surface']['min'];
    if ( isset( $args['surface']['max'] )  )
      $whereClause[] = 'surfaceData.meta_value <= ' . $args['surface']['max'];
    if ( isset( $args['price']['min'] ) )
      $whereClause[] = 'priceData.meta_value >= ' . $args['price']['min'];
    if ( isset( $args['price']['max'] ) )
      $whereClause[] = 'priceData.meta_value <= ' . $args['price']['max'];
    if ( isset( $args['rooms'] )  )
      $whereClause[] = 'roomsData.meta_value  >= ' . $args['rooms'];
    if ( isset( $args['bathRooms'] ) )
      $whereClause[] = 'bathRoomsData.meta_value >= ' . $args['bathRooms'];
    if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
      $whereClause[] = 'typeData.meta_value IN ("' . implode( '","', $args['types'] ) . '")';

    $stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts 
    INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = wp_posts.ID AND surfaceData.meta_key = 'surface' 
    INNER JOIN wp_postmeta as priceData ON priceData.post_id = wp_posts.ID AND priceData.meta_key = 'price'
    INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = wp_posts.ID AND roomsData.meta_key = 'bedrooms_count' 
    INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = wp_posts.ID AND bathRoomsData.meta_key = 'bathrooms_count'
    INNER JOIN wp_postmeta as typeData ON typeData.post_id = wp_posts.ID AND typeData.meta_key = 'type'    
    WHERE post_author = :hotelId AND post_type = 'room'" . ( ! empty( $whereClause ) ? ' AND ' . implode( ' AND ', $whereClause ) : '' ) . " ORDER BY priceData.meta_value ASC LIMIT 1" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );

    /**
     * On convertit les lignes en instances de chambres (au passage ça charge toutes les données).
     *
     * @var RoomEntity[] $rooms ;
     */
    $rooms = array_map( function ( $row ) {
      return $this->getRoomService()->get( $row['ID'] );
    }, $stmt->fetchAll( PDO::FETCH_ASSOC ) );
    
    // On exclut les chambres qui ne correspondent pas aux critères
    $filteredRooms = [];
    
    foreach ( $rooms as $room ) {
      if ( isset( $args['surface']['min'] ) && $room->getSurface() < $args['surface']['min'] )
        continue;
      
      if ( isset( $ar 
      ['surface']['max'] ) && $room->getSurface() > $args['surface']['max'] )
        continue;
      
      if ( isset( $args['price']['min'] ) && intval( $room->getPrice() ) < $args['price']['min'] )
        continue;
      
      if ( isset( $args['price']['max'] ) && intval( $room->getPrice() ) > $args['price']['max'] )
        continue;
      
      if ( isset( $args['rooms'] ) && $room->getBedRoomsCount() < $args['rooms'] )
        continue;
      
      if ( isset( $args['bathRooms'] ) && $room->getBathRoomsCount() < $args['bathRooms'] )
        continue;
      
      if ( isset( $args['types'] ) && ! empty( $args['types'] ) && ! in_array( $room->getType(), $args['types'] ) )
        continue;
      
      $filteredRooms[] = $room;
    }
    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( count( $filteredRooms ) < 1 )
      throw new FilterException( "Aucune chambre ne correspond aux critères" );
    
    
    // Trouve le prix le plus bas dans les résultats de recherche
    $cheapestRoom = null;
    foreach ( $filteredRooms as $room ) :
      if ( ! isset( $cheapestRoom ) ) {
        $cheapestRoom = $room;
        continue;
      }
      
      if ( intval( $room->getPrice() ) < intval( $cheapestRoom->getPrice() ) )
        $cheapestRoom = $room;
    endforeach;
    $timer->endTimer('TimerGetCheapestRoom', $timerId);
    return $cheapestRoom;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
    
    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}