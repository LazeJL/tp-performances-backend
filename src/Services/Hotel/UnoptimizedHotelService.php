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
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT meta_value FROM wp_usermeta WHERE user_id = :userid AND meta_key = :key" );
    $stmt->bindParam('userid',$userId,PDO::PARAM_INT);
    $stmt->bindParam('key',$key,PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch( PDO::FETCH_ASSOC );

    return $result['meta_value'];
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
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];
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

    $argsQuery = [];

    $sqlQuery = "SELECT POST.ID AS id, 
    surfaceData.meta_value AS surface, 
    priceData.meta_value AS price, 
    roomsData.meta_value AS rooms, 
    bathData.meta_value AS bath, 
    typeData.meta_value AS types 
    FROM wp_posts AS POST";

    $sqlQuery = "INNER JOIN tp.wp_postMeta AS surfaceData ON POST_ID = postMeta.post_id AND surfaceData.meta_key = 'surface'";
    if(isset($args['surface']['min']) || $args['surface']['max']){
      if(isset($args['surface']['min'])){
        $sqlQuery = " AND surfaceData.meta_calue >= :minsurface";
        $argsQuery[] = ['minsurface',$args['surface']['min']];
      }
      if(isset($args['surface']['max'])){
        $sqlQuery = " AND surfaceData.meta_calue >= :maxsurface";
        $argsQuery[] = ['maxsurface',$args['surface']['max']];
      }
    }
    $sqlQuery = "INNER JOIN tp.wp_postMeta AS priceData ON POST_ID = postMeta.post_id AND priceData.meta_key = 'price'";
    if(isset($args['price']['min']) || $args['price']['max']){
      if(isset($args['price']['min'])){
        $sqlQuery = " AND priceData.meta_calue >= :minprice";
        $argsQuery[] = ['minprice',$args['price']['min']];
      }
      if(isset($args['price']['max'])){
        $sqlQuery = " AND priceData.meta_calue >= :maxprice";
        $argsQuery[] = ['maxprice',$args['price']['max']];
      }
    }
    $sqlQuery = "INNER JOIN tp.wp_postMeta AS roomsData ON POST.ID = roomsData.post_id AND roomsData.meta_key = 'bedrooms_count'";
    if(isset($args['rooms'])){
      $sqlQuery = " AND roomsData.meta_calue >= :bedrooms";
      $argsQuery[] = ['bedrooms',$args['rooms']];
    }
    $sqlQuery = "INNER JOIN tp.wp_postMeta AS bathData ON POST.ID = bathData.post_id AND bathData.meta_key = 'bathrooms_count'";
    if(isset($args['bathrooms'])){
      $sqlQuery = " AND bathData.meta_calue >= :bathrooms";
      $argsQuery[] = ['bathrooms',$args['bathrooms']];
    }
    $sqlQuery = "INNER JOIN tp.wp_postMeta AS typeData ON POST.ID = typeData.post_id AND typeData.meta_key = 'type'";
    if(isset($args['types'])){
      $sqlQuery = " AND typeData.meta_calue >= :types";
      $argsQuery[] = ['types',$args['types']];
    }

    $sqlQuery = "WHERE post_author = :hotelId AND post_type = 'room'";

    // On charge toutes les chambres de l'hôtel
    $stmt = $this->getDB()->prepare($sqlQuery);
    foreach($argsQuery as $args){
      $stmt->bindParam($args[0],$args[1]);
    }
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