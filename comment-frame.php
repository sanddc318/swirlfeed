<?php
  require("config/config.php");
  include("includes/classes/User.php");
  include("includes/classes/Post.php");
  include("includes/classes/Notification.php");

  if ( isset($_SESSION["username"]) ) {
    $loggedInUser = $_SESSION["username"];
    $user_details_query = mysqli_query( $con, "SELECT * FROM users WHERE username = '$loggedInUser'" );
    $user = mysqli_fetch_array( $user_details_query );
  } else {
    header( "Location: register.php" );
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title></title>
  <link href="https://fonts.googleapis.com/css?family=Merienda+One" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

  <style>
    body, html {
      background-color: transparent;
      font-size: 12px;
      font-family: Arial, Helvetica, sans-serif;
    }
  </style>


  <script>
    function toggle() {
      var element = document.getElementById("comment-section");

      if ( element.style.display == "block" ) {
        element.style.display = "none";
      } else {
        element.style.display = "block";
      }
    }
  </script>


  <?php
    // Get id of post
    if ( isset($_GET["post_id"]) ) {
      $post_id = $_GET["post_id"];
    }

    $user_query = mysqli_query( $con, "SELECT added_by, user_to FROM posts
                                       WHERE id = '$post_id'" );
    $row = mysqli_fetch_array( $user_query );
    $posted_to = $row["added_by"];
    $user_to = $row["user_to"];

    if ( isset($_POST["postComment" . $post_id]) ) {
      $post_body = $_POST["post-body"];
      $post_body = mysqli_escape_string( $con, $post_body );
      $date_time_now = date( "Y-m-d H:i:s" );
      $insert_post = mysqli_query( $con, "INSERT INTO comments
                                          VALUES ('', '$post_body', '$loggedInUser', '$posted_to', '$date_time_now', 'no', '$post_id' )" );

      if ($posted_to != $loggedInUser) {
        $notification = new Notification($con, $loggedInUser);
        $notification->insertNotification($post_id, $posted_to, "post-comment");
      }

      if ($user_to != "none" && $user_to != $loggedInUser) {
        $notification = new Notification($con, $loggedInUser);
        $notification->insertNotification($post_id, $user_to, "profile-comment");
      }

      $get_commenters = mysqli_query($con, "SELECT * FROM comments
                                            WHERE post_id = '$post_id'");
      $notified_users = array();

      while ($row = mysqli_fetch_array($get_commenters)) {
        if ($row["posted_by"] != $posted_to && $row["posted_to"] != $user_to
            && $row["posted_by"] != $loggedInUser && !in_array($row["posted_by"], $notified_users)) {
          $notification = new Notification($con, $loggedInUser);
          $notification->insertNotification($post_id, $row["posted_by"], "comment-non-owner");

          array_push($notified_users, $row["posted_by"]);
        }
      }

      echo "<p>Comment posted</p>";
    }
  ?>


  <form id="comment-form"
        action="comment-frame.php?post_id=<?php echo $post_id; ?>"
        method="POST"
        name="postComment<?php echo $post_id; ?>"
  >
    <textarea name="post-body" placeholder="Say something!"></textarea>
    <input type="submit" name="postComment<?php echo $post_id; ?>" value="Comment">
  </form>

  <!-- Load comments -->
  <?php
    $get_comments = mysqli_query( $con, "SELECT * FROM comments
                                         WHERE post_id = '$post_id'
                                         ORDER BY id ASC" );
    $count = mysqli_num_rows( $get_comments );

    if ( $count != 0 ) {
      while( $comment = mysqli_fetch_array($get_comments) ) {
        $comment_body = $comment["post_body"];
        $posted_to = $comment["posted_to"];
        $posted_by = $comment["posted_by"];
        $date_added = $comment["date_added"];
        $removed = $comment["removed"];

        // Get a timestamp
        $date_time_now = date( "Y-m-d H:i:s" );
        $start_date = new Datetime( $date_added ); // Time of post creation
        $end_date = new Datetime( $date_time_now ); // Latest activity
        $interval = $start_date->diff( $end_date );

        if ( $interval->y >= 1 ) { // Years

              if ( $interval == 1 ) {
                $time_message = $interval->y . " year ago";
              } else {
                $time_message = $interval->y . " years ago";
              }

        } else if ( $interval->m >= 1 ) { // If at least a month old

              if ( $interval->d == 0 ) {
                $days = " ago"; // If exactly a month, just add "ago" (e.g. "4 months ago")
              } else if ( $interval->d == 1 ) {
                // Otherwise...
                $days = $interval->d . " day ago"; // "1 day ago"
              } else {
                $days = $interval->d . " days ago"; // "(n) days ago"
              }

              // Now concatenate the month(s) and day(s)
              if ( $interval->m == 1 ) {
                $time_message = $interval->m . " month," . $days; // e.g. "1 month, 6 days ago"
              } else {
                $time_message = $interval->m . " months," . $days; // e.g. "8 months, 1 day ago"
              }

        } else if ( $interval->d >= 1 ) {

              if ( $interval->d == 1 ) {
                $time_message = "Yesterday"; // If exactly one day, just say "yesterday"
              } else {
                $time_message = $interval->d . " days ago";
              }

        } else if ( $interval->h >= 1 ) { // Hours

              if ( $interval->h == 1 ) {
                $time_message = $interval->h . " hour ago";
              } else {
                $time_message = $interval->h . " hours ago";
              }

        } else if ( $interval->i >= 1 ) { // Minutes

              if ( $interval->i == 1 ) {
                $time_message = $interval->i . " minute ago";
              } else {
                $time_message = $interval->i . " minutes ago";
              }

        } else { // Seconds

              if ( $interval->s < 30 ) {
                $time_message = "Just now";
              } else {
                $time_message = $interval->s . " seconds ago";
              }

        } // End timestamp code
        $user_obj = new User( $con, $posted_by );
  ?>

        <div class="comment-section">
          <a href="<?php echo $posted_by; ?>" target="_parent">
            <img src="<?php echo $user_obj->getProfilePic(); ?>"
                title="<?php echo $posted_by; ?>"
                style="float: left;"
                height="30"
            >
          </a>
          <a href="<?php echo $posted_by; ?>" target="_parent">
            <b><?php echo $user_obj->getUsername(); ?></b>
          </a> &nbsp;&nbsp;&nbsp;&nbsp; <?php echo $time_message . "<br>" . $comment_body; ?>
          <hr>
        </div>

  <?php
      } // End while loop
    } else {
      echo "<center><br><br>Be the first to comment!</center>";
    } // End if block $count != 0
  ?>
</body>
</html>
