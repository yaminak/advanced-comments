<?php 
include 'config.php';
try {
    $pdo = new PDO('mysql:host=' . db_host . ';dbname=' . db_name . ';charset='
           . db_charset, db_user, db_pass);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $exception){

//if there is an error with the connection, stop the script and display the error
exit('Failed to connect to database!');

}

//convert datetime to time elapsed string
//convertir datetime en chaîne de temps écoulé
function time_elapsed_string($datetime, $full = false){
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 
                    'h' => 'hour', 'i' => 'minute', 's' => 'second');

foreach ($string as $k => &$v) {
    if($diff->$k){
        $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
    }else{
        unset($string[$k]);
        }
    }//end of foreach

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';

}

function show_comment($comment, $comments = [], $filters = []){
    //convert new lines
    $content = nl2br(htmlspecialchars($comment['content'], ENT_QUOTES));

    //allow html tags
    $content = str_ireplace(
            ['&lt;i&gt;', '&lt;/i&gt', '&lt;b&gt;', '&lt;u&gt;', '&lt;/u&gt;',
                '&lt;/u&gt;', '&lt;code&gt;', '&lt;.code&gt;', '&lt;pre&gt;', 
                '&lt;/pre&gt;'],
            ['<i>', '</i>', '<b>', '</b>', '<u>', '</u>', '<code>', '</code>',
                '<pre>', '</pre>'],

                $content
    );
    //apply the filters
    if($filters){
        $content = str_ireplace(array_column($filters, 'word'), array_column($filters, 'replacement'), $content);  
    }
    //comment template
    $html = '
      <div class="comment">
        <div class="img">
            <img src="' . (!empty($comment['img']) ? htmlspecialchars($comment
                ['img'], ENT_QUOTES) : default_profile_image) . '" width="48" height="48" alt="Comment Profile Image">
        </div>

        <div class="con">
            <div>
                <h3 class="name">' . htmlspecialchars($comment['name'], ENT_QUOTES) . ' </h3>
                <br>
                <span class="date">' . time_elapsed_string($comment['submit_date']) . '</span>
            </div>

            <p class="comment_content">
            ' . $content . '
            ' . ($comment['approved'] ? '' : '<br><br><i>(Awaiting approval)</i>') . '
            </p>
            <div class="comment_footer">
                <span class="num">' . $comment['votes'] . '</span>
                <a href="#" class="vote" data-vote="up" data-comment-id="' . $comment['id'] .'">
                <i class="arrow up"></i>
                </a>
                <a href="#" class="vote" data-vote="down" data-comment-id="' . $comment['id'] .'">
                <i class="arrow down"></i>
                </a>
                <a class="reply_comment_btn" href="#" data-comment-id="' . $comment['id'] . '">
               Reply</a>

            </div>
            ' . show_write_comment_form($comment['id']) . '
            <div class="replies">
            ' . show_comments($comments, $filters, $comment['id']) . '
            </div>
        </div>
      </div>';
      return $html;
}

// function who show comments

function show_comments($comments, $filters, $parent_id = -1) {
    $html = ''; 
    if($parent_id != -1){
        array_multisort(array_column($comments, 'submit_date'), SORT_ASC, $comments);
    }//end of if
    foreach($comments as $comment){
        if($comment ['parent_id'] == $parent_id){
            $html .= show_comment($comment, $comments, $filters);
        }  
    }
    return $html;
}//end of function

function show_write_comment_form($parent_id = -1){
    $html = '
        <div class="write_comment" data-comment-id="' . $parent_id .'">
            <form>
                <input name="parent_id" type="hidden" value="' . $parent_id . '">
                <input name="name" type="text" placeholder="Your Name" required>
                <textarea name="content" placeholder="Write your comment here..." required>
                </textarea>
                <input name="img_url" type="url" placeholder="Photo Icon URL (optional)">
                <button type="submit">Submit</button>
            </form>
        </div>
    ';
    return $html;
}

// page id need to be create, to determine which comment are for which page

if(isset($_GET['page_id'])){
    //retrieve the filters
    //récupérer les filtres
    $stmt = $pdo->prepare('SELECT * FROM filters');
    $stmt->execute();
    $filters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //if the user submit the form 
    if(isset($_POST['name'], $_POST['content'], $_POST['parent_id'], $_POST['img_url']))
    {
        //insert a new comment
        $stmt = $pdo->prepare('INSERT INTO comments (page_id, parent_id, name, content, submit_date, img, approved) VALUES (?,?,?,?,NOW(),?,?)');
        $approved = comments_approval_required ? 0 : 1;
        $stmt->execute([ $_GET['page_id'], $_POST['parent_id'], $_POST['name'], $_POST['content'], $_POST['img_url'], $approved]);
        //retrieve the comment
        $stmt = $pdo->prepare('SELECT * FROM comments WHERE id = ?');
        $stmt->execute([ $pdo->lastInsertId() ]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        //output the comment
        //sortir le commentaire
        exit(show_comment($comment));
    }
    //vote buttons, need to add the number
    if(isset($_GET['vote'], $_GET['comment_id'])){
        //check if the cookie exist for this comment
        if(!isset($_COOKIE['vote_' . $_GET['comment_id']])) {
            //if cookie does not exist update the vote column (increment or decrement the value)
            $stmt = $pdo->prepare('UPDATE comments SET votes = votes ' . ($_GET['vote'] == 'up' ? '+' : '-') . ' 1 WHERE id = ?');
            $stmt->execute([$_GET['comment_id'] ]);

            //set cookie to prevent users from voting multiple times
            setcookie('vote_' . $_GET['comment_id'], 'true', time() + ( 10 * 365 * 24 * 60 * 60), '/');
        }//end of cookie
        //retrieve the number of vote from the comment
        $stmt = $pdo->prepare('SELECT votes FROM comments WHERE id = ? ');
        $stmt->execute([$_GET['comment_id'] ]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        //output the vote
        exit($comment['votes']); 
    }
    //if the limit variable exist, add the limit close to the sql statement
    // si la variable limite existe, il faut ajouter la limite dans l'instruction sql

    $comments_per_pagination_page = isset($_GET['comments_to_show']) ? $_GET['comments_to_show'] : 30;
    $limit = isset($_GET['current_pagination_page']) ? 'LIMIT :current_pagination_page' : '';
    $sort_by = 'ORDER BY votes DESC, submit_date DESC';
    
    if(isset($_GET['sort_by'])){
        
        //user has changed the sort by
        $sort_by = $_GET['sort_by'] == 'newest' ? 'ORDER BY submit_date DESC' : $sort_by;
        $sort_by = $_GET['sort_by'] == 'oldest' ? 'ORDER BY submit_date ASC' : $sort_by;
        $sort_by = $_GET['sort_by'] == 'votes' ? 'ORDER BY votes DESC, submit_date DESC' : $sort_by;
    }
    //to get all comments by the page Id
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE page_id = :page_id AND approved = 1 ' . $sort_by . ' ' . $limit);
   
    if($limit){
       
        $stmt->bindValue(':current_pagination_page', (int)$_GET['current_pagination_page']*(int)$comments_per_pagination_page, PDO::PARAM_INT);
        
    }
    //bind the page Id to our query
    // lier l'identifiant de la page à notre requête

    $stmt->bindValue(':page_id', $_GET['page_id'], PDO::PARAM_INT);
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //get the total number of comments
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total_comments FROM comments WHERE page_id = ? AND approved = 1');
    $stmt->execute([$_GET['page_id'] ]);
    $comments_info = $stmt->fetch(PDO::FETCH_ASSOC); 

   
    
}
else{
    
    exit('No page ID specified!');
}

?> <!--End of php -->

<div class="comment_header">
    <span class="total"><?=$comments_info['total_comments']?> comments</span>
    <form class="" action="index.html" method="post">
        <label for="sort_by"></label>
        <select name="sort_by" id="sort_by">
            <option value="" disabled<?=!isset($_GET['sort_by']) ? 'selected' : ''?>>Sort by</option>
            <option value="votes"<?=isset($_GET['sort_by']) && $_GET['sort_by'] == 'votes' ? ' selected' : ''?>>Votes</option>
            <option value="newest"<?=isset($_GET['sort_by']) && $_GET['sort_by'] == 'newest' ? ' selected' : ''?>>Newest</option>
            <option value="oldest"<?=isset($_GET['sort_by']) && $_GET['sort_by'] == 'oldest' ? ' selected' : ''?>>Oldest</option>
        </select>
        <a href="#" class="write_comment_btn" data-comment-id="-1">Write Comment</a>
    </form>
    
</div>

<?=show_write_comment_form()?>

    <div class="comments_wrapper">
        <?=show_comments($comments, $filters)?>  
    </div>


<?php if(count($comments) < $comments_info['total_comments']): ?>

    <a href="#" class="show_more_comments">Show More</a>

<?php endif; ?>
