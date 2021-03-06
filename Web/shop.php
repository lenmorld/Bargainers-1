<?php
include('php-python/db_connect.php');              //needed for DB connection

date_default_timezone_set('UTC');

//print_r ($_POST);

if (isset($_POST['submitSearch']))
{

	$query = $_POST['submitSearch'];
	$JSONNCIXresult = exec('python ./php-python/NCIXsearch.py ' . $query );
	$JSONNeweggresult = exec('python ./php-python/newegg.py ' . $query );

	//echo $JSONNCIXresult;
    //echo $JSONNeweggresult;

	//There is an error with the blank space, there are some items that do not appear in the search result
    //Does not give price at all
    //searching "check" gives us 4 blank results


	$allProducts = array();

	$JSONNCIXresult = trim($JSONNCIXresult, '[');       //remove the extra brackets
	$JSONNCIXresult = trim($JSONNCIXresult, ']');

	if($JSONNCIXresult !== ''){
	    $JSONNCIXresult = str_replace("}, {", "}==x=={" , $JSONNCIXresult);
        $itemNCIXArray = explode("==x==", $JSONNCIXresult);


        foreach ($itemNCIXArray as $value) {
        //\xa0 is actually non-breaking space in Latin1 (ISO 8859-1), also chr(160). You should replace it with a space.

            $itemNCIXExplode = str_replace('\xa0', ' ', $value);
            $itemNCIXExplode = str_replace("'", '"' , $itemNCIXExplode);                  //replace single quotes with double quotes
            $itemNCIXExplode = str_replace(': u"', ': "' , $itemNCIXExplode);      //remove unicode mark
            array_push($allProducts, $itemNCIXExplode);
        }
	}

    $JSONNeweggresult = trim($JSONNeweggresult, '[');       //remove the extra brackets
	$JSONNeweggresult = trim($JSONNeweggresult, ']');

	if($JSONNeweggresult !== ''){
	    $JSONNeweggresult = str_replace("}, {", "}==x=={" , $JSONNeweggresult);
        $itemNeweggArray = explode("==x==", $JSONNeweggresult);

        foreach ($itemNeweggArray as $value2) {
        //\xa0 is actually non-breaking space in Latin1 (ISO 8859-1), also chr(160). You should replace it with a space.

            $itemNeweggExplode = str_replace('\xa0', ' ', $value2);
            $itemNeweggExplode = str_replace("'", '"' , $itemNeweggExplode);                  //replace single quotes with double quotes
            $itemNeweggExplode = str_replace(': u"', ': "' , $itemNeweggExplode);      //remove unicode mark
            array_push($allProducts, $itemNeweggExplode);
        }
    }


//############## GET CHEAPEST FROM DATABASE ####################
//    $conn = setUpConnection();

//SELECT A.webID, MIN(A.lowestPrice) AS minPrice
        //FROM
        //(

//######### CHEAPEST IN DB #####################


//    $sql = "SELECT *
//       FROM Product
//        WHERE lowestPrice =  (SELECT MIN(lowestPrice)
//        FROM Product
//       WHERE name  LIKE '%" . $query   ."%') ;";

//    $result = $conn->query($sql);


//    if ($result->num_rows > 0) {

    // $cheapestInDB = $result->fetch_assoc();

    //echo 'results found:';

    // while($row = $result->fetch_assoc()) {
    //    echo $row['lowestPrice'];
    // }
// }

//    $conn->close();

    //######################################################

    //array_pop($allProducts);          //pop weird empty element at the end
    //########################### SAVE TO DATABASE #############################

    // session_start();

    //$authenticated = false;


    $productList = array();
    //prepare database connection

    $conn = setUpConnection();

    for ($y = 0; $y < count($allProducts); $y++) {
        $currentItem = json_decode($allProducts[$y], true);         //decode JSON from python script

        //each JSON is in associative (Key-value pair) format
        // "URL"=>"", "Name"=>"", "Price"=>0.00, ...
        //hashing the URL to get a webID
        $webID =  md5($currentItem["URL"])  ;

        //###### CHECK if current product is already in Database #########

        $sql = "SELECT * FROM Product WHERE webID = '" . $webID . "';";

        // echo $sql;
        $duplicate = $conn->query($sql);

        // echo ("numrows:" . $duplicate->num_rows);

        //FOR CHECKING, print the duplicates
        //while($row = $duplicate->fetch_assoc()) {
        //echo $row["webID"];
        //}

        if ($duplicate->num_rows === 0)          //if there is no duplicates
        {
            //need to attach '' for MySQL strings
            $webID =  "'" . $webID . "'"   ;

            $productID =  substr($currentItem["Name"], 20);
            $productID = "'" . str_replace(' ', '', $productID) ."'" ;      //strip spaces

            $name = "'" . $currentItem["Name"] . "'" ;
            $URL = "'" . $currentItem["URL"] . "'" ;
            $lowestPrice = str_replace(',', '', $currentItem["Price"]);
            $photoURL = "'" . $currentItem["Photo"] . "'";

            //$currentItem["webID"] = $webID;
        	//prepared statements
                /*	$stmt = $conn->prepare("INSERT INTO Product (webID, name, URL, productID, lowestPrice, DateAdded, photoURL, description )
                    	                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    	$stmt->bind_param("ssssdsss", $webID, $name, $URL, $productID, $lowestPrice, date('Y-m-d h:i:s'),  $photoURL, "some description");
                    	$stmt->execute();
                */

            $date = "'" . date('Y-m-d h:i:s') . "'";

            $sql = "INSERT INTO Product (webID, name, URL, productID, lowestPrice, DateAdded, photoURL, description ) VALUES ( $webID, $name, $URL, $productID, $lowestPrice,$date , $photoURL, 'some description')";

                   // echo $sql;
            $conn->query($sql);
                // ERROR INFO
            //if ($conn->query($sql) === TRUE);
            //else
            //    echo "Error: " . $sql . "<br>" . $conn->error;
        }

        else{
            $row = $duplicate->fetch_assoc();
            if(str_replace(',', '', $currentItem["Price"]) < $row["lowestPrice"]){

                $newPrice = str_replace(',', '', $currentItem["Price"]);
                $date = "'" . date('Y-m-d h:i:s') . "'";

                $sql = " UPDATE Product SET lowestPrice = $newPrice, DateAdded = $date
                         WHERE webID = '" . $webID . "';" ;

                $conn->query($sql);
            }
        }
    } //	end for

    $conn->close();
}

  //###################################################################


else if(isset($_POST['str2php'])){

    //echo "WISHLIST SAVE";
    //echo $_POST['str2php'];

//now this is an comma-separated values that corresponds to webID of seleted Items
//echo $_POST['selectedWebIDs'];
//this string should then be saved to the database, indicating the users wishlist

    $userID = 1;
    $username = "user1";
    $password = "userone";
    $address = "123 Fake Street";
    $phone = "123-456-7810";

    $conn = setUpConnection();

    //Create query to find the product list of a certain user
    $theQuery = "SELECT *
                FROM UserAccount
                WHERE username ='" . $username . "'" .
                " AND password ='" . $password . "'" .
                " AND productList <> '';";

    //Store result in previous
    $previous = $conn->query($theQuery);

    //If previous has some results
    if ($previous->num_rows > 0)
    {
        $row = $previous->fetch_assoc();
        $previousList = $row['productList'];
        $currentList = $_POST['str2php'];

    //TODO establish user AUTHENTICATION and registration
    //SAVE INTO database, update productList (WISHLIST) of logged-in user
    // we assume a user is logged in and these info are already stored in database

        $sql =  " UPDATE UserAccount SET productList = '" . $currentList . "," . $previousList . "'" .
                " WHERE username ='" . $username . "'" .
                " AND password ='" . $password . "';";

        echo $sql;
    }

    else
    {
        $currentList = $_POST['str2php'];

    //TODO establish user AUTHENTICATION and registration
    //SAVE INTO database, update productList (WISHLIST) of logged-in user
    // we assume a user is logged in and these info are already stored in database

        $sql = " UPDATE UserAccount SET productList ='" . $currentList . "'" .
               " WHERE username ='" . $username . "'" .
               " AND password ='" . $password . "';";

        echo $sql;
    }


    if ($conn->query($sql) === TRUE)  ;

    else
        echo "Update Error: " . $sql . "<br>" . $conn->error;

    $conn->close();

}

?>


<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="google-site-verification" content="PtlTebFoue90iB2Sc9zKLJRERVBuDYqTO50mBJqCgt0"/>
        <meta name="viewport" content="initial-scale=1.0; maximum-scale=1.0; width=device-width;">
        <link rel="stylesheet" href="..//css/searchToolbar.css">
        <link rel="stylesheet" href="..//css/shoptable.css">
        <link rel="stylesheet" href="..//css/viewpage.css">
        <link rel="stylesheet" href="..//css/sidebar.css">
        <link href='//fonts.googleapis.com/css?family=Source+Sans+Pro:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800' rel='stylesheet' type='text/css'>
    <title>The Bargainers</title>
    </head>


    <body class="shoppage">
        <div class="setSide-BarColor"></div>

        <nav class="main-menu">
            <ul class="upper-side">
                <li class="main-menu-list">
                    <p><i class="fa fa-users fa-2x"></i> <span class="nav-text">Guest</span></p>
                </li>

                <li class="main-menu-list">
                    <a href="homepage_userloggedIn.html"><i class="fa fa-home fa-2x"></i> <span class=
                    "nav-text">Home</span></a>
                </li>

                <li class="main-menu-list">
                    <a href="shop.php"><i class="fa fa-shopping-cart fa-2x"></i> <span class=
                    "nav-text">Search Products</span></a>
                </li>

                <li class="main-menu-list">
                    <a href="wishlist.php"><i class="fa fa-list fa-2x"></i> <span class=
                    "nav-text">Your Wishlist</span></a>
                </li>
            </ul>

            <ul class="bottom-side">
                <li class="main-menu-list">
                    <a href="accountsettings.html"><i class="fa fa-wrench fa-2x"></i>
                    <span class="nav-text">Account Settings</span></a>
                </li>

                <li class="main-menu-list">
                    <a href="logout.php"><i class="fa fa-power-off fa-2x"></i> <span class=
                    "nav-text">Logout</span></a>
                </li>
            </ul>
        </nav>

    <div id="main">
        <!-- TOP BANNER -->
        <div role="banner" class="hellobox">
            <h1 class="shop">The Bargainers - Shop for your products </h1>
        </div>

        <!-- WISHLIST ICON -->
        <div id="wishMenu">
            <table id="wishListMenu">
                    <thead>
                        <tr>
                            <th class="text-left">List of Items</th>
                        </tr>
                    </thead>

                <tbody id="tableBody">
                    <!-- FILLED AS USER SELECTS ITEM FROM THE SEARCH RESULT TABLE-->
                </tbody>
            </table>

            <span>
                <input id="submitWish" type="submit" value="Add to List" name="submitWish">
                <input id="cancelWish" type="submit" value="Cancel" name="cancelWish">
            </span>
        </div>

        <!-- CONTENT PAGE -->
        <div class="scrollablePage" role="dynamicPage">

            <!-- SEARCH SECTION -->
            <div id="searchSection" class="beforeResults">
                <div id="searchQuery">
                    <form method="POST" name="productSearch" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
	                    <input id="submitSearch" name="submitSearch" type="search" placeholder="Search">
                    </form>
                </div>


                <div id="sCategories" class="searchCriteria">
                    <p>Price</p>
                        <!-- Options -->

                    <p>Retailer</p>
                        <!-- Options -->

                    <p>Date of Lowest Price</p>
                        <!-- Options -->

                </div>

            </div>

            <!-- TABLE RESULT SECTION -->
            <div id='viewingTable'>
                <div class='table-shop'>
                    <?php
                    // Table appears when there are search results
                    if (isset($_POST['submitSearch'])){

                    echo "<table id='tableSearch' class='resultsTable'>";
                        echo "<thead>";
                            echo "<tr>";
                                echo "<th id='tablePhoto' class='text-left'></th>";
                                echo "<th id='tableName' class='text-left'>Name</th>";
                                echo "<th id='tableURL' class='text-left'>Retailer</th>";
                                echo "<th id='tableDate' class='text-left'>Date</th>";
                                echo "<th id='tableLowPrice' style='color:red' class='text-left'>Lowest Price</th>";
                                echo "<th id='tablePrice' class='text-left'>Price</th>";
                            echo "</tr>";
                        echo "</thead>";

                        echo "<tbody class='table-hover'>";
                            echo "<div>";
                                //Not exactly sure why I don't need another '>'
                                //When I add another '>' to end the statement, a '>' gets printed on the page
                                echo '<form id="myForm" method="POST" name="productWish" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);php?>';


                              	    if (isset($allProducts))

                                    for ($x = 0; $x < count($allProducts); $x++) {
                                        $currentItem = json_decode($allProducts[$x], true);
                                        $webID =  md5($currentItem["URL"]);
                                        //ADDED TO PRODUCE the webID
                                        $productID =  substr($currentItem["Name"], 20);
                                        $productID = "'" . str_replace(' ', '', $productID) ."'" ;      //strip spaces


                                            //TODO
                                            //echo "<input type='checkbox'/>";
                                            //accumulate the webID and productID, a selection mechanism should determine only the ones selected
                                            //need a script which checks which ones are selected

                                            // $webIDtemp .=  md5($currentItem["URL"]) .  ",";
                                        $name = $currentItem["Name"];
                                        $url = $currentItem["URL"];
                                        $price = str_replace(',', '', $currentItem["Price"]);
                                        $photo = $currentItem["Photo"];

        	    						//########### GET THE PRICE OF THIS PRODUCT FROM DATABASE ########
		    	    					$conn = setUpConnection();
			    	    				$sql = "SELECT lowestPrice
				    	    					FROM Product
					    	    				WHERE webID ='"  . $webID  . "';";

    									$result = $conn->query($sql);

										if ($result->num_rows > 0)
    									{
	    								    $row = $result->fetch_assoc();
    	    								$lowestPrice =  $row['lowestPrice'];
	    	    						}

	    		    					else
	    			    				{
	    				    			    continue; //THIS ONLY HAPPENS IF THERE IS NO LOWEST PRICE, BUT THERE WILL ALWAYS BE ONE
	    					    		}


	    					    		$sql = "SELECT DateAdded
				    	    					FROM Product
					    	    				WHERE webID ='"  . $webID  . "';";

                                        $result = $conn->query($sql);

										if ($result->num_rows > 0)
    									{
	    								    $row = $result->fetch_assoc();
    	    								$date = $row["DateAdded"];
    	    								$date = substr($date, 0, 10);
    	    							}

			    						$conn->close();
    									//###################################################

                                        //SHORTENING THE LINKS

                                        $tempWebName = stristr(stristr($currentItem["URL"], "www.", false), ".com", true);
                                        $tempWebName = strtoupper(substr($tempWebName, 4));

                                        //ACCOUNTING FOR .CA WEBSITES

                                        if($tempWebName === ""){
                                            $tempWebName = stristr(stristr($currentItem["URL"], "www.", false), ".ca", true);
                                            $tempWebName = strtoupper(substr($tempWebName, 4));
                                        }

                                        //###################################################

                                        echo ' <tr id=searchR' . $x . '> ';
                                        echo '<td class="text-left"><img src="' . $currentItem["Photo"]  . '" height="60" width="80">';
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='webID' name='webID' value='$webID' />
                                            </div>";
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='Photo' name='Photo' value='$photo' />
                                            </div>";
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='name' name='name' value='$name' />
                                            </div>";
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='Date' name='date' value='$date' />
                                            </div>";
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='Price' name='Price' value='$price' />
                                            </div>";
                                        echo "<div style='display:none;'>
                                            <input type='hidden' id='URL' name='url' value='$url' />
                                            </div>";
                                        echo "</td>";

                                        echo '<td class="text-left">' . $currentItem["Name"]  . '</td>';
                                        echo '<td class="text-right"><a target="_blank" href=" ' . $currentItem["URL"]  . ' "> ' . $tempWebName .  ' </td>';
		        						echo '<td class="text-right">' . $date  . '</td>';
		        						echo '<td style="color:red" class="text-right">$' . $lowestPrice  . '</td>';
                                        echo '<td class="text-right"> $' . $currentItem["Price"]  . '</td>';
                                        echo "</tr>";
                                    } //	echo $result;
                                        //THIS WOULD PASS THE webID to POST for PHP when Save to Wishlist is clicked
                                        //HIDDEN INPUT

               	            echo "</form>";
                        echo "</div>";
	                echo "</tbody>";
                echo "</table>";
                }
                ?>
	            </div>
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.0/angular.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
    <script type="text/javascript" src="..//js/shop.js"></script>
    <script type="text/javascript" src="..//js/sortingTable.js"></script>

    <script type="text/javascript">
        $("table thead th:eq(1)").data("sorter", false);
        $(document).ready(function(){
            $("#tableSearch").tablesorter({ sortList: [[5,0]] });
        });
    </script>


    </body>
</html>