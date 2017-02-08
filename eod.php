<?php
include("userclass.php");
// Parse without sections
$ini_array= parse_ini_file("configure.ini");
  //echo $ini_array["CAMPSITE_ID"]."\n";

  //get the user count from gitter room
  $url="https://api.gitter.im/v1/rooms?access_token=".$ini_array["API_KEY"];

  //  Initiate curl
  $ch = curl_init();
  // Disable SSL verification
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  // Will return the response, if false it print the response
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // Set the url
  curl_setopt($ch, CURLOPT_URL,$url);
  // Execute
  $result=curl_exec($ch);
  // Closing
  curl_close($ch);


  $object = json_decode($result);

  $user_count=$object[2]->userCount;
  echo $object[2]->userCount."\n";
  //got the user count in $user_count variable
  userUpdate($user_count);
  //var_dump($temp);

  //user updation in table is finished
  //select the users from user table and add them to the user class
  $link = mysqli_connect('localhost','root','','mini');
    if (!$link) {
      die('Could not connect to MySQL: ' . mysql_error());
    }

    $qry="SELECT * FROM `user` WHERE `excluder`='N'";
    $res=mysqli_query($link,$qry) or die (mysqli_error($link));
    //array for user list
    $user_list=array();
    //insert the fetched data to the array
    while($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
      $user_list[]=new user($row["uid"],$row["uname"],$row["name"]);
    }

    $url_list=array();
    for($i=0;$i<count($user_list);$i++)
      $url_list[]=$user_list[$i]->apiurl;

    //var_dump($url_list);

    $chunkArray=array_chunk($url_list,10);

    //var_dump(count($chunkArray));
    $result=array();
    for($i=0;$i<count($chunkArray);$i++){
      echo $i." hi\n";
      $temp=multiRequest($chunkArray[$i]);
      $result = array_merge($result, $temp);
      sleep(3);
    }

    for($i=0;$i<count($user_list);$i++){
      $object=json_decode($result[$i], true);
      if(isset($object["about"]["browniePoints"]))
        $user_list[$i]->points= $object["about"]["browniePoints"];
      else
        $user_list[$i]->points= 0;
    }

    //var_dump($temp[0]);

   print_r($user_list);
   //data inserted
   //sort the data based on th points
   function cmp($a, $b)
   {
       if ($a->points == $b->points) {
         return 0;
     }
     return ($a->points > $b->points) ? -1 : 1;
   }
   usort($user_list,"cmp");
   print_r($user_list);
   $total_points=0;

/*   //data insertion to the table
   for($i=0;$i<count($user_list);$i++){
     $total_points += $user_list[$i]->points;
     $qry="INSERT INTO `daily_update`(`r_date`, `uid`, `points`, `rank`) VALUES ('". date("Y-m-d") ."','".$user_list[$i]->id."',".$user_list[$i]->points.",".($i+1).")";
     //echo $qry."\n";
     $res=mysqli_query($link,$qry) or die (mysqli_error($link));
     //echo $res."\n";
   }*/
    $total_points=addUserData($user_list);
    dailyUpdate($total_points,count($user_list));
   //daily update table is updated
   //". date("Y-m-d") ."
   /*$qry="INSERT INTO `daily_count`(`u_date`, `pts_count`, `u_count`) VALUES ('". date("Y-m-d") ."',". $total_points.",".count($user_list).")";
   $res=mysqli_query($link,$qry) or die (mysqli_error($link));*/
   echo $total_points;
   echo "done";



   //userUpdate function()
  function userUpdate($user_count){
        $ini_array= parse_ini_file("configure.ini");

        $link = mysqli_connect('localhost','root','','mini');
          if (!$link) {
            die('Could not connect to MySQL: ' . mysql_error());
          }

        $url_array=array();
        $user_list_new=array();
        //array for user list
           for($x=0;$x<=$user_count+100; $x=$x+100){
               $url="https://api.gitter.im/v1/rooms/".$ini_array["CAMPSITE_ID"]."/users?access_token=".$ini_array["API_KEY"]."&limit=100&skip=".$x;
               $url_array[]=$url;
           }
        //var_dump($url_array);
        $result=multiRequest($url_array);

        for($i=0;$i<count($result);$i++)
            $user_list_new[]=json_decode($result[$i]);

          //var_dump($user_list_new);
         //user updation and insertion
         for($x=0;$x<count($user_list_new);$x++){
             for($y=0;$y<count($user_list_new[$x]);$y++){
               $abc[]=$user_list_new[$x][$y];
               $qry="SELECT count(*) FROM `user` WHERE `uid`='".$user_list_new[$x][$y]->id."'";
               $res=mysqli_query($link,$qry) or die (mysqli_error($link));

               while($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
                   $dbcount=$row["count(*)"];
                }
                   if($dbcount==0){
                     $qry="INSERT INTO `user`(`uid`, `name`, `doj`, `uname`, `url`) VALUES ('".$user_list_new[$x][$y]->id."','".$user_list_new[$x][$y]->displayName."','". date("Y-m-d") ."','".$user_list_new[$x][$y]->username."','".$user_list_new[$x][$y]->avatarUrlMedium."')";
                     //echo $qry."\n";
                   }else{
                     $qry ="UPDATE `user` SET `url`='".$user_list_new[$x][$y]->avatarUrlMedium."',`name`='".$user_list_new[$x][$y]->displayName."',`uname`='".$user_list_new[$x][$y]->username."' WHERE `uid`='".$user_list_new[$x][$y]->id."'";
                     //echo $qry."\n";
                   }
               $res=mysqli_query($link,$qry) or die (mysqli_error($link));
               echo $qry."\n";

             }
         }
  }


  function addUserData($user_list){
    $total_points=0;
    $link = mysqli_connect('localhost','root','','mini');
      if (!$link) {
        die('Could not connect to MySQL: ' . mysql_error());
      }
    //data insertion to the table
    for($i=0;$i<count($user_list);$i++){
      $total_points += $user_list[$i]->points;
      //echo date("Y-m-d")."\n";
      $qry="SELECT count(*) FROM `daily_update` WHERE `r_date`='". date("Y-m-d") ."' AND `uid`='".$user_list[$i]->id."'";
      $flag=mysqli_query($link,$qry) or die (mysqli_error($link));
      while($row = mysqli_fetch_array($flag, MYSQL_ASSOC)) {
          $dbcount=$row["count(*)"];
       }
      if($dbcount==0){
        $qry="INSERT INTO `daily_update`(`r_date`, `uid`, `points`, `rank`) VALUES ('". date("Y-m-d") ."','".$user_list[$i]->id."',".$user_list[$i]->points.",".($i+1).")";
      }else{
        $qry="UPDATE `daily_update` SET  `points`=".$user_list[$i]->points.", `rank`=".($i+1)." WHERE `uid`='".$user_list[$i]->id."' AND `r_date`='". date("Y-m-d") ."'";
      }
      //echo $qry."\n";
      $res=mysqli_query($link,$qry) or die (mysqli_error($link));
      //echo $res."\n";
    }
    return $total_points;
  }

  function dailyUpdate($total_points,$user_count){
    $link = mysqli_connect('localhost','root','','mini');
      if (!$link) {
        die('Could not connect to MySQL: ' . mysql_error());
      }
      $qry="SELECT count(*) FROM `daily_count` WHERE `u_date`='". date("Y-m-d") ."'";
      $flag=mysqli_query($link,$qry) or die (mysqli_error($link));
      while($row = mysqli_fetch_array($flag, MYSQL_ASSOC)) {
          $dbcount=$row["count(*)"];
       }
       if($dbcount==0){
         $qry="INSERT INTO `daily_count`(`u_date`, `pts_count`, `u_count`) VALUES ('". date("Y-m-d") ."',". $total_points.",".$user_count.")";
         //echo $qry."\n";
       }else{
         $qry ="UPDATE `daily_count` SET `pts_count`='".$total_points."', `u_count`=".$user_count." WHERE `u_date`='". date("Y-m-d") ."'";
         //echo $qry."\n";
       }
      $res=mysqli_query($link,$qry) or die (mysqli_error($link));
  }


  function multiRequest($data, $options = array()) {
	  // array of curl handles
	  $curly = array();
	  // data to be returned
	  $result = array();
	  // multi handle
	  $mh = curl_multi_init();
	  // loop through $data and create curl handles
	  // then add them to the multi-handle
	  foreach ($data as $id => $d) {
	    $curly[$id] = curl_init();
	    $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
	    curl_setopt($curly[$id], CURLOPT_URL,$url);
	    curl_setopt($curly[$id], CURLOPT_HEADER,0);
	    curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER,1);
      curl_setopt($curly[$id], CURLOPT_SSL_VERIFYPEER,false);
      // Will return the response, if false it print the response
      curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER,true);
	    // post?
	    if (is_array($d)) {
  	      if (!empty($d['post'])) {
        		curl_setopt($curly[$id], CURLOPT_POST,1);
        		curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
  	      }
	    }
	   // extra options?
	    if (!empty($options)) {
	      curl_setopt_array($curly[$id], $options);
	    }
	    curl_multi_add_handle($mh, $curly[$id]);
	  }
	  // execute the handles
	  $running = null;
    $count=0;
	  do {
      echo $count++."\n";
	    curl_multi_exec($mh, $running);
	  } while($running > 0);

	  // get content and remove handles
	  foreach($curly as $id => $c) {
	    $result[$id] = curl_multi_getcontent($c);
	    curl_multi_remove_handle($mh, $c);
	  }
	  // all done
	  curl_multi_close($mh);
	  return $result;
	}
?>
