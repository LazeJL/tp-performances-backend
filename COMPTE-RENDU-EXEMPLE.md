Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : 31.1

**Choix des méthodes à analyser** :

- `GetMetas` 4.54 sec
- `GetCheapestRoom` 16.73 sec
- `GetReview` 8.96 sec



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 31.1 \ 29.1

**Temps consommé par `getDB()`** 

- **Avant** 1.36 sec

- **Après** 2.88 ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 29.1 sec

- **Après** 24.4 sec


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 4.54 sec

```sql
SELECT * FROM wp_usermeta
```

- **Après** 1.62 sec

```sql
'SELECT meta_value FROM wp_usermeta WHERE user_id = :user_id AND meta_key = :meta_key'
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 8.86 sec

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 7.66 sec

```sql
SELECT ROUND(AVG(meta_value)) AS rating, COUNT(meta_value) AS count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 16.73 sec

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 13.10 sec
```sql
SELECT * FROM wp_posts
INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = wp_posts.ID AND surfaceData.meta_key = 'surface'
INNER JOIN wp_postmeta as priceData ON priceData.post_id = wp_posts.ID AND priceData.meta_key = 'price'
INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = wp_posts.ID AND roomsData.meta_key = 'bedrooms_count'
INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = wp_posts.ID AND bathRoomsData.meta_key = 'bathrooms_count'
INNER JOIN wp_postmeta as typeData ON typeData.post_id = wp_posts.ID AND typeData.meta_key = 'type'
WHERE post_author = '200' AND post_type = 'room' AND surfaceData.meta_value >= 130 AND surfaceData.meta_value <= 150 AND priceData.meta_value >= 200 AND priceData.meta_value <= 230 AND roomsData.meta_value  >= 5 AND bathRoomsData.meta_value >= 5 AND typeData.meta_value IN ("Maison","Appartement") ORDER BY priceData.meta_value ASC LIMIT 1
```



## Question 5 : Réduction du nombre de requêtes SQL pour `getMetas`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201    | 601    |
 | Temps de `getMetas`            | 4.54 sec     | 1.35 sec     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601    | 1    |
| Temps de chargement global   | 24.4 sec     | 3 sec     |

**Requête SQL**

```SQL
-- GIGA REQUÊTE
-- INDENTATION PROPRE ET COMMENTAIRES SERONT APPRÉCIÉS MERCI !
SELECT
        user.ID AS id,
        user.display_name AS name,
        address_1Data.meta_value       as hotel_address_1,
        address_2Data.meta_value       as hotel_address_2,
        address_cityData.meta_value    as hotel_address_city,
        address_zipData.meta_value     as hotel_address_zip,
        address_countryData.meta_value as hotel_address_country,
        geo_latData.meta_value         as geo_lat,
        geo_lngData.meta_value         as geo_lng,
        phoneData.meta_value           as phone,
        coverImageData.meta_value      as coverImage,
        postData.ID                    as cheapestRoomid,
        postData.price                 as price,
        postData.surface               as surface,
        postData.bedroom               as bedRoomsCount,
        postData.bathroom              as bathRoomsCount,
        postData.type                  as type,
        COUNT(reviewData.meta_value)   as ratingCount,
        AVG(reviewData.meta_value)     as rating 
        FROM

        wp_users AS USER

        INNER JOIN wp_usermeta as address_1Data       ON address_1Data.user_id       = USER.ID     AND address_1Data.meta_key       = 'address_1'
        INNER JOIN wp_usermeta as address_2Data       ON address_2Data.user_id       = USER.ID     AND address_2Data.meta_key       = 'address_2'
        INNER JOIN wp_usermeta as address_cityData    ON address_cityData.user_id    = USER.ID     AND address_cityData.meta_key    = 'address_city'
        INNER JOIN wp_usermeta as address_zipData     ON address_zipData.user_id     = USER.ID     AND address_zipData.meta_key     = 'address_zip'
        INNER JOIN wp_usermeta as address_countryData ON address_countryData.user_id = USER.ID     AND address_countryData.meta_key = 'address_country'
        INNER JOIN wp_usermeta as geo_latData         ON geo_latData.user_id         = USER.ID     AND geo_latData.meta_key         = 'geo_lat'
        INNER JOIN wp_usermeta as geo_lngData         ON geo_lngData.user_id         = USER.ID     AND geo_lngData.meta_key         = 'geo_lng'
        INNER JOIN wp_usermeta as coverImageData      ON coverImageData.user_id      = USER.ID     AND coverImageData.meta_key      = 'coverImage'
        INNER JOIN wp_usermeta as phoneData           ON phoneData.user_id           = USER.ID     AND phoneData.meta_key           = 'phone'
        INNER JOIN wp_posts    as rating_postData     ON rating_postData.post_author = USER.ID     AND rating_postData.post_type    = 'review'
        INNER JOIN wp_postmeta as reviewData          ON reviewData.post_id = rating_postData.ID   AND reviewData.meta_key          = 'rating'
    
        INNER JOIN (SELECT
            post.ID,
            post.post_author,
            MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
            CAST(surfaceData.meta_value  AS UNSIGNED) AS surface,
            CAST(roomsData.meta_value AS UNSIGNED) AS bedroom,
            CAST(bathRoomsData.meta_value AS UNSIGNED) AS bathroom,
            typeData.meta_value AS type

            FROM

            tp.wp_posts AS post
            INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
            INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
            INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
            INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
            INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'

            WHERE
            post.post_type = 'room'

            GROUP BY
            post.ID
        ) AS postData ON user.ID = postData.post_author

        GROUP BY user.ID
        ORDER BY `cheapestRoomId` ASC
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `wp_usermeta` : `user_id`
- `wp_postmeta` : `post_id`
- `wp_posts` : `post_author`

**Requête SQL d'ajout des indexes** 

```sql
-- REQ SQL CREATION INDEXES
ALTER TABLE `wp_postmeta` ADD INDEX(`post_id`);
ALTER TABLE `wp_usermeta` ADD INDEX(`user_id`);
ALTER TABLE `wp_posts` ADD INDEX(`post_author`);
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 30 sec       | 1.4 sec        |
| `OneRequestService`            | 3 sec      | 1.2 sec        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `rooms` (1 200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
