<?php
   session_start();
   
   // Connexion à la base de données
    $db = new PDO( 
    'mysql:host=localhost;dbname=movies', 
    'root', '',   
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    //Search Part you can search a full title like "the lords of the rings" it will works :)
    if(isset($_REQUEST["search"])){
        $search = $_REQUEST["search"];
        $pattern='/(\s)/i';//Find space to replace with +                                                       
        $source = file_get_contents("https://www.imdb.com/find?q=".preg_replace($pattern, '+', $search).'&s=tt&ref_=fn_al_tt_mr');
        preg_match_all('/<tr class="findResult \w+"> <td class="primary_photo"> <a href="\/title\/(\w+)\//i', $source, $link);
        preg_match_all('/<img src="([^"]+)" \/><\/a> <\/td> <td class="result_text">/i', $source, $limg);
        preg_match_all('/<td class="result_text"> <a href="[^"]+" >([^<]+)/i', $source, $movie);
    }
    //Movie part
    if(isset($_REQUEST["link"])){
        $source = file_get_contents("https://www.imdb.com/title/".$_REQUEST["link"]."");
        //Title and image
        preg_match('/"url": "\/title\/.+\/",\n  "name": "(.+)",\n  "image": "(.+)",/i', $source, $match);
        
        //Description, date, rating
        preg_match('/"description": "(.+)",\n  "datePublished": "(\d{4}).+",\n  "keywords": ".+",\n  "aggregateRating": {\n.+\n    "ratingCount": .+,\n    "bestRating": ".+",\n    "worstRating": ".+",\n    "ratingValue": "(.+)"/i', $source, $match2);

        //Character and actors
        preg_match_all('/<a href="\/title\/tt\d+\/characters\/[\s\S].+" >([\w\s:].+)<\/a>/i', $source, $characters);
        preg_match_all('/<a href="\/name\/(nm\d+)\/[\s\S].+"\n> ([\w\s:].+)/i', $source, $actors);
        
        $title = $match[1];
        $image = $match[2];
        $description=$match2[1];
        $date= $match2[2];
        $rating=$match2[3];
       
        $id = $actors[1];
        $name = $actors[2];
        
        //Keep in session for the comment 
        $_SESSION['link']=$_REQUEST['link'];
        
        /**SAVE THE MOVIE TO THE BDD */
        // Check if the id of the movie is already in use
        $result = $db->prepare(
            "SELECT * FROM movie WHERE link='".$_REQUEST['link']."'",
            [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
        ); 
        $result->execute();
        
        $row = $result->fetch(PDO::FETCH_ASSOC);
        
        $data = [
            ':title' => $title,
            ':image' => $image,
            ':rating' => $rating,
            ':date' => $date,
            ':link' => $_REQUEST['link'],
        ];
       
        if(!$row){
            //If the movie doesnt exist add the movie into the bdd
            $response = $db->prepare(
                "INSERT INTO movie (link, title, date, image, rating) VALUES (:link, :title, :date, :image, :rating)",
                [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
            ); 
            $response->execute($data);
        } 
    }
    /**SAVE THE NOTE AND COMMENT INTO THE BDD */
    if(isset($_REQUEST["note"])){
        $data = [
        ':comment' => $_REQUEST["comment"],
        ':note' => $_REQUEST["note"],
        ':link' => $_SESSION['link'],
     ];
    $response = $db->prepare(
        "UPDATE movie SET note=:note, comment=:comment WHERE link=:link",
        [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
     ); 
    $response->execute($data);
    }
   
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-eOJMYsd53ii+scO/bJGFsiCZc+5NDVN2yr8+0RDqr0Ql0h+rP48ckxlpbzKgwra6" crossorigin="anonymous">
    <style type="text/css">
    * {
        -webkit-font-smoothing: antialiased;
    }
    body{
    background-color: #212529;
    color: white;
    }

    svg{
        color: yellow;
    }

    .container{
        margin-top: 50px;  
    }

    .h1{
        top: 50px;
    }
    </style>

    <title>Mipam Movies</title>
</head>
    <body>
    <!--Search PART-->
        <div class="container">
            <h1 class="text-center">Welcome to Mipam Movies !</h1>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg">
                    <form method="POST"> 
                        <div class="input-group mb-3">
                            <!--Search bar-->
                            <input type="text" name="search" class="form-control mb-5" placeholder="Search movies or series...">
                            <button class="btn btn-outline-light mb-5" type="submit">Search !</button>
                        </div>
                    </form>
                    <table class="table table-dark table-striped align-middle">
                        <?php
                        if(!isset($_REQUEST["search"])&&!isset($_REQUEST["link"])){//My top 10 movies on the first page before the search
                            echo'<thead>';
                            echo '<h2 class="text-center">My top 15 Movies and Series :</h2>'; 
                            echo '<tr><th scope="col">Note</th><th scope="col">Image</th><th scope="col">Title</th><th scope="col">Comment</th><th scope="col">Button</th></tr>';
                            echo'</thead><tbody>';
                            for ($i=0; $i<15 ;$i++){//loop to select my top 10 movies
                            
                                $result = $db->prepare(
                                    "SELECT link, note, title, image, comment FROM movie ORDER BY note DESC",
                                    [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                                ); 
                                $result->execute();
                                $row = $result->fetchAll(PDO::FETCH_ASSOC);

                                echo '<tr class="align-middle">
                                    <td><h4>'.$row[$i]['note'].'</h4></td>
                                    <td><img src="'.$row[$i]['image'].'"width=70px></td>
                                    <td><h5>'.$row[$i]['title'].'</h5></td>
                                    <td><p>'.$row[$i]['comment'].'</p></td>
                                    <td><form><input type="hidden" name="link" value="'.$row[$i]['link'].'">
                                    <button type="submit" value="Submit" class="btn btn-outline-light">See more</button></form></td></tr>
                                    </tbody>';
                                }
                            }

                            if(isset($_REQUEST["search"])){
                                echo'<tbody><thead>';
                                echo '<tr><th scope="col">Image</th><th scope="col">Title</th><th scope="col">Button</th></tr>'; 
                                echo'</thead>';
                            }
                            /**List all the movie with the name research */
                            if(isset($_REQUEST["search"])){
                                for ($i=0, $count = count($movie[1]); $i<$count ;$i++){  
                                    echo'<td><img src="'.$limg[1][$i].'"width=70px></td>';
                                    echo'<td><h4>'.$movie[1][$i].'</h4></td>
                                    <td><form><input type="hidden" name="link" value="'.$link[1][$i].'">
                                    <button type="submit" value="Submit" class="btn btn-outline-light">See more</button></form></td></tr></tbody>';
                                }
                            }
                        ?> 
                    </table>
                </div>
            </div>
        </div>
    <!--End Search PART-->
    <!--Movie PART-->
    <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <?php 
                    //Image of the movie
                    if(isset($_REQUEST["link"])&&!isset($_REQUEST["search"])){
                    echo '<img class=mt-5 src="'.$image.'" alt="" width=300px>'; 
                    echo '<a href="index.php" class="btn btn-outline-light m-5">Return to my top 10</a>';
                    
                    }?>
                </div>
                <div class="col-lg-7">
                <?php
                /**TITLE RATING DATE AND DESCRIPTION */
                if(isset($_REQUEST["link"])&&!isset($_REQUEST["search"])){
                    echo '<h1 class=mt-5>'.$title.'</h1>';
                    echo '<p>Rating of viewers : '.$rating.'/10 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star-fill" viewBox="0 0 16 16">
                    <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                    </svg></p>';
                    echo '<p>Release date : '.$date.'</p>';
                    echo '<h3 class=mt-2>Description:</h3><p class=mt-3>'.$description.'</p>';
                    
                    /**NOTE AND COMMENT BDD + FORM */
                    $result = $db->prepare(//Check if comment and note exist
                        "SELECT * FROM movie WHERE link='".$_REQUEST["link"]."' AND note IS NULL AND comment IS NULL",
                        [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                    ); 
                    $result->execute();
                    
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    
                    if($row){//Comment and note doesnt exist so user can post is comment
                        echo '<form name="rate">
                            <div class="mb-3">
                                <select class="form-select" name="note">
                                    <option selected value="">Choose your note</option>
                                    <option value="1">Fuck this movie : 1</option>
                                    <option value="2">Reimburse me : 2</option>
                                    <option value="3">Are you kidding me : 3</option>
                                    <option value="4">Shame : 4</option>
                                    <option value="5">Medium : 5</option>
                                    <option value="6">It can do better : 6</option>
                                    <option value="7">Good : 7</option>
                                    <option value="8">Very good : 8</option>
                                    <option value="9">Excellent : 9</option>
                                    <option value="10">Best movie of the universe : 10</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <textarea class="form-control" id="comment" rows="3" name="comment" placeholder="Leave your review"></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-light">Submit</button>
                        </form>';  
                    }
                    else{
                        //If the comment and note exist it will show the comment and the note
                        
                        $result = $db->prepare(//Check if comment and note exist
                            "SELECT comment, note FROM movie WHERE link='".$_REQUEST["link"]."'",
                            [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                        ); 
                        $result->execute();
                        $row = $result->fetch(PDO::FETCH_ASSOC);

                        echo("<p>Your note: <b>".$row['note']."/10</b> <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-star-fill' viewBox='0 0 16 16'>
                        <path d='M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z'/>
                        </svg></p>");
                        echo("<h5>Your comment: </h5><p><i>".$row['comment']."</i></p>");
                    };
                }
                ?> 
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                        <?php
                         /**FULL CAST**/
                        if(isset($_REQUEST["link"])&&!isset($_REQUEST["search"])){
                            echo '<h2>Full Cast:</h2>';
                            }
                        ?>
                <div class="col-xs">
                    <table class="table table-dark table-striped">
                        <thead>
                            <?php
                            if(isset($_REQUEST["link"])&&!isset($_REQUEST["search"])){
                                echo '<tr><th scope="col">#</th><th scope="col">Role</th><th scope="col">Actors</th></tr>'; 
                                }
                            ?>
                        </thead>
                        <tbody>
                            <?php
                            /**It will show every actors with their roles*/
                            if(isset($_REQUEST["link"])&&!isset($_REQUEST["search"])){
                                for ($i=0, $count = count($name); $i<$count ;$i++){
                                    $j=$i+1;
                                    echo '<tr><th scope="row">'.$j.'<td>'.$characters[1][$i].'</td><td>'. $name[$i].'</td></tr>';
                                    
                                    /**ACTORS PART BDD**/
                                    
                                    $result = $db->prepare(//Check if actor exist
                                        "SELECT * FROM actors WHERE id='".$id[$i]."'",
                                        [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                                    ); 
                                    $result->execute();
                                    
                                    $row = $result->fetch(PDO::FETCH_ASSOC);
                                    
                                    $data = [
                                        ':name' => $name[$i],
                                        ':id' => $id[$i], 
                                    ];
                                    if(!$row){
                                        //If the actors doesnt exist the actor is added into the bdd
                                        $response = $db->prepare(
                                            "INSERT INTO actors (id, name) VALUES (:id, :name)",
                                        
                                            [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                                        ); 
                                        $response->execute($data); 
                                    };
                                    
                                    /**ROLE PART BDD**/
                                    $id_role=$_REQUEST['link'].$id[$i]; //Special id for the role combining id of actor and id of the movies so it will be unique
                                    $result2 = $db->prepare(//Check if the role exist
                                        "SELECT * FROM role WHERE id_role='".$id_role."'",
                                        [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                                    ); 
                                    $result2->execute();
                                    
                                    $row2 = $result2->fetch(PDO::FETCH_ASSOC);
                                   
                                    $data2 = [
                                        ':link' => $_REQUEST['link'],
                                        ':role' => $characters[1][$i],
                                        ':id' => $id[$i],
                                        ':id_role' => $id_role,
                                    ];
                                    
                                    if(!$row2){
                                        //If the role of the actors doesnt exist it add the role into the bdd 
                                        $response2 = $db->prepare(
                                            
                                            "INSERT INTO role (id, link, role, id_role) VALUES (:id, :link, :role, :id_role)",
                                            [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
                                        ); 
                                        $response2->execute($data2);
                                    };
                                } 
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>