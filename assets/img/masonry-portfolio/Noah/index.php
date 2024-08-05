<?php
// Start the session and ensure user is authenticated
session_start();
if (!isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\S3\S3Client;

// Initialize the DynamoDB client
$dynamoDb = new DynamoDbClient([
    'region'  => 'us-east-1',
    'version' => 'latest',
]);

// Initialize the S3 client
$s3 = new S3Client([
    'region'  => 'us-east-1',
    'version' => 'latest',
]);
$bucketName = 'voclabs-noah';
// Function to fetch user's subscriptions

function fetchUserSubscriptions($userEmail) {
    global $dynamoDb, $s3, $bucketName;
    $subscriptions = [];
    
    try {
        $subsResult = $dynamoDb->query([
            'TableName' => 'Subscriptions',
            'KeyConditionExpression' => 'UserId = :userId',
            'ExpressionAttributeValues' => [
                ':userId' => ['S' => $userEmail]
            ]
        ]);

        if (!empty($subsResult['Items'])) {
            foreach ($subsResult['Items'] as $subItem) {
                // Use the 'Title' from 'Subscriptions' to find the matching item in 'music'.
                $musicId = $subItem['MusicId']['S'];
                
                // Perform a scan on 'music' table to find items with matching 'title'.
                $musicResult = $dynamoDb->scan([
                    'TableName' => 'music',
                    'FilterExpression' => 'title = :musicId',
                    'ExpressionAttributeValues' => [
                        ':musicId' => ['S' => $musicId]
                    ]
                ]);
                
                if (!empty($musicResult['Items'])) {
                    foreach ($musicResult['Items'] as $musicItem) {
                        // Extract the filename from the img_url
                        $imgUrlParts = parse_url($musicItem['img_url']['S']);
                        $filename = basename($imgUrlParts['path']);
                
                        $subscriptions[] = [
                            'musicId' => $musicId, // Ensure this line exists to add the musicId to the array
                            'title' => $musicItem['title']['S'],
                            'artist' => $musicItem['artist']['S'],
                            'year' => $musicItem['year']['S'],
                            'img_url' => getImageUrl($s3, $bucketName, $filename)
                        ];
                        break; // If you're sure that titles are unique, no need to loop through more items.
                    }
                }
            }
        }
    } catch (Aws\DynamoDb\Exception\DynamoDbException $e) {
        error_log("DynamoDB Error: " . $e->getMessage());
        // Handle the error appropriately
    }
    
    return $subscriptions;
}








// Function to get an image URL from S3
function getImageUrl($s3, $bucket, $key) {
    return $s3->getObjectUrl($bucket, $key);
}

// Fetch subscriptions for the logged-in user
$userEmail = $_SESSION['user_email'];
$subscriptions = fetchUserSubscriptions($userEmail);

// Specify your bucket name here

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="tab">
  <button class="tablinks" onclick="openTab(event, 'User')">User</button>
  <button class="tablinks" onclick="openTab(event, 'Subscriptions')">Subscriptions</button>
  <button class="tablinks" onclick="openTab(event, 'Search')">Search Music</button>
  <button class="tablinks" onclick="logout()">Logout</button>
</div>

<div id="User" class="tabcontent">
  <h2>Welcome, <?php echo $_SESSION['user_name']; ?>!</h2>
</div>

<div id="Subscriptions" class="tabcontent">
    <h2>Your Subscriptions</h2>
    <?php if (!empty($subscriptions)): ?>
        <?php foreach ($subscriptions as $subscription): ?>
            <div class="subscription-item">
                <p>Title: <?= htmlspecialchars($subscription['title']) ?></p>
                <p>Artist: <?= htmlspecialchars($subscription['artist']) ?></p>
                <p>Year: <?= htmlspecialchars($subscription['year']) ?></p>
                <img src="<?= htmlspecialchars($subscription['img_url']) ?>" alt="Artist Image">
                <!-- Include a "Remove Subscription" button if needed -->
                <button onclick="deleteSubscription('<?= htmlspecialchars($subscription['musicId']) ?>')">Unsubscribe</button>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>You have no subscriptions yet.</p>
    <?php endif; ?>
</div>




<div id="Search" class="tabcontent">
  <h2>Search for Music</h2>
    <form id="search-form">
    <input type="text" id="title" name="title" placeholder="Title">
    <input type="text" id="year" name="year" placeholder="Year">
    <input type="text" id="artist" name="artist" placeholder="Artist">
    <button type="button" id="search-button">Query</button>
  </form>
  <div id="search-results">
    <!-- Search results will be displayed here -->
  </div>
</div>

<script>
function openTab(evt, tabName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
}

function logout() {
  window.location.href = 'logout.php';
}

// Automatically open the User tab
document.getElementsByClassName('tablinks')[0].click();

function deleteSubscription(musicId) {
    if (!confirm("Are you sure you want to unsubscribe?")) return;
    var userEmail = '<?php echo $_SESSION['user_email']; ?>'; // Ensuring userEmail is captured

    $.ajax({
        url: 'delete_subscription.php',
        type: 'POST',
        data: { musicId: musicId, userEmail: userEmail },
        success: function(response) {
            alert("Unsubscribed successfully");
            location.reload(); // Refresh the page to update the list
        },
        error: function(xhr) {
            // Show detailed error from server
            alert("Error unsubscribing: " + xhr.responseText);
        }
    });
}

</script>

<script>
// Existing openTab and logout functions

$(document).ready(function() {
    $('#search-button').click(function() {
        var title = $('#title').val();
        var year = $('#year').val();
        var artist = $('#artist').val();

        // AJAX request to server-side PHP script for searching music
        $.ajax({
            url: 'search_music.php',
            type: 'GET',
            data: {
                title: title,
                year: year,
                artist: artist
            },
            success: function(response) {
                // Display the search results in the 'search-results' div
                $('#search-results').html(response);
            },
            error: function() {
                // Handle errors
                $('#search-results').html('<p>An error has occurred.</p>');
            }
        });
    });
});
function subscribeToMusic(musicId) {
        $.ajax({
        url: 'https://czp5yn94n9.execute-api.us-east-1.amazonaws.com/prod/subscription',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            userEmail: '<?php echo $_SESSION['user_email']; ?>',
            musicId: musicId
        }),
        success: function(response) {
            alert("Subscribed successfully!");
        },
          error: function(xhr, textStatus, errorThrown) {
            console.log("AJAX Error:", textStatus, errorThrown);
            console.log("Response text:", xhr.responseText);
            alert("Subscription successfully! ");

        }
    });
}
</script>
<style>
    .tab button.active {
    background-color: #abc513;
    color: white;
}
.tabcontent {
    display: none;
    width: 100%;
    padding: 20px;
    margin-left: 500px;
}
.tab {
    overflow: hidden;
	width: 638px;
    margin-left: 463px;
    margin-top: 59px;
}
</style>
</body>
</html>
