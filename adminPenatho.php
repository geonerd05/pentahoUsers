<?php

//credentials for the pentaho admin account
$credentials = 'admin:password';
//home of the pentaho webapp
$base = 'http://localhost:8080/pentaho';
//entry point for all web pentaho web service apis
$apis = $base.'/api';
//entry point for pentaho's user admin web service api
$userroledao = $apis.'/userroledao';

//our addition, variables that contains file and role names 

$filenameCSV="myfile.csv";
$roleAssign="my_role";

//utility to send credentials with each curl request
//required to authenticate against pentaho.
function authenticate($curl_handle) {
    global $credentials;
    curl_setopt($curl_handle, CURLOPT_USERPWD, $credentials);
}

//generic method to do a GET request. Returns the response
function get_curl_doc($url, $accept){
    $c = curl_init();
    authenticate($c);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($c, CURLOPT_HTTPHEADER, array(
        'Accept: '.$accept
    ));

    $return = curl_exec($c);
    curl_close($c);
    if ($return === FALSE) {
        return NULL;
    }
    return $return;
}

//do a GET request and obtain the response as an XML document
function get_curl_xml_doc($url){
    $xml = get_curl_doc($url, 'application/xml');
    $xml = simplexml_load_string($xml);
    return $xml;
}

//get all pentaho users (as XML document)
function get_users(){
    global $userroledao;
    $xml = get_curl_xml_doc($userroledao.'/users');
    return $xml;
}

//get roles for a particular user (as XML document)
function get_user_roles($user){
    global $userroledao;
    $user = urlencode($user);
    $xml = get_curl_xml_doc($userroledao.'/userRoles?userName='.$user);
    return $xml;
}

//get all pentaho roles (as XML document)
function get_roles(){
    global $userroledao;
    $xml = get_curl_xml_doc($userroledao.'/roles');
    return $xml;
}

//get a list of users that have a particular role (as XML document)
function get_role_members($role){
    global $userroledao;
    $role = urlencode($role);
    $xml = get_curl_xml_doc($userroledao.'/roleMembers?roleName='.$role);
    return $xml;
}

//get the "logical role map".
//This contains basic system privileges and role assignment info (as XML document)
function get_privileges(){
    global $userroledao;
    $xml = get_curl_xml_doc($userroledao.'/logicalRoleMap');
    return $xml;
}

//find a particular role in logical role map
function get_role_privilege_assignment($privileges, $role) {
    foreach ($privileges->assignments as $assignment) {
        if ($assignment->roleName == $role) {
            return $assignment;
        }
    }
    return NULL;
}

//get an array of privileges associated with the given role assignment
function get_role_privileges($assignment) {
    $logicalRoles = array();
    if ($assignment) {
        foreach ($assignment->logicalRoles as $logicalRole) {
            array_push($logicalRoles, ''.$logicalRole);
        }
    }
    return $logicalRoles;
}

//create a role or a user.
function create($type, $name, $password){
    global $userroledao;
    $url = $userroledao.'/create'.$type;
    $c = curl_init();
    authenticate($c);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
    $headers = array(
        'Accept: application/xml'
    );
    switch ($type) {
        case 'User':
            $body = '<user>'.
                '<userName>'.$name.'</userName>'.
                '<password>'.$password.'</password>'.
                '</user>';
            array_push($headers, 'Content-Type: application/xml');
            curl_setopt($c, CURLOPT_POSTFIELDS, $body);
            break;
        case 'Role':
            $url .= '?roleName='.$name;
            break;
    }
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);

    curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return $status;
}

//create the specified role
function create_role($role){
    $status = create('Role', $role, NULL);
    return $status;
}

//create a user with specified name and password.
function create_user($user, $password){
    $status = create('User', $user, $password);
    return $status;
}

//delete users or roles.
function delete($type, $items) {
    global $userroledao;
    switch ($type) {
        case 'User':
        case 'Role':
            break;
        default:
            return NULL;
    }
    if (is_array($items)) {
        $items = implode("\t", $items);
    }
    if (is_string($items)) {
        $items = urlencode($items);
    }
    $url = $userroledao.'/delete'.$type.'s?'.lcfirst($type).'Names='.$items;
    $c = curl_init();
    authenticate($c);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($c, CURLOPT_URL, $url);

    curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return $status;
}

//delete the specified roles
function delete_roles($roles){
    $status = delete('Role', $roles);
    return $status;
}

//delete the specified users
function delete_users($users){
    $status = delete('User', $users);
    return $status;
}

//assign roles to a user, or users to a role.
function assign($type, $name1, $value1, $name2, $value2){
    global $userroledao;
    $url = $userroledao.'/'.$type.
        '?'.$name1.'='.urlencode($value1).
        '&'.$name2.'='.urlencode($value2)
    ;
    $c = curl_init();
    authenticate($c);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($c, CURLOPT_URL, $url);
    curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return $status;
}

//assign the specified role to the specified user.
function assign_user_role($user, $role){
    return assign('assignRoleToUser', 'userName', $user, 'roleNames', $role);
}

//unassign the specified role from the specified user.
function unassign_user_role($user, $role){
    return assign('removeRoleFromUser', 'userName', $user, 'roleNames', $role);
}

//assign the specified user to the specified role.
function assign_role_user($role, $user){
    return assign('assignUserToRole', 'roleName', $role, 'userNames', $user);
}

//unassign the specified user from the specified role.
function unassign_role_user($role, $user){
    return assign('removeUserFromRole', 'roleName', $role, 'userNames', $user);
}

//assign privileges to a role.
function assignPrivileges($role, $privileges){
    global $userroledao;
    $url = $userroledao.'/roleAssignments';
    $c = curl_init();
    authenticate($c);
    $headers = array(
        'Accept: application/xml',
        'Content-Type: application/xml'
    );
    $privileges = implode('</logicalRoles><logicalRoles>', $privileges);
    if ($privileges) {
        $privileges = '<logicalRoles>'.$privileges.'</logicalRoles>';
    }
    $body = '<?xml version="1.0" encoding="UTF-8"?>'.
        '<logicalRoleAssignments>'.
        '<assignments>'.
        '<roleName>'.$role.'</roleName>'.
        $privileges.
        '</assignments>'.
        '</logicalRoleAssignments>'
    ;
    curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return $status;
}

//assign or unassign the specified privilige to or from the specified role
function assign_or_unassign_role_privilege($role, $privilege, $assign){
    $privileges = get_privileges();
    $role_privilege_assignment = get_role_privilege_assignment($privileges, $role);
    $role_privileges = get_role_privileges($role_privilege_assignment);
    if ($assign) {
        array_push($role_privileges, $privilege);
    }
    else {
        unset($role_privileges[array_search($privilege, $role_privileges)]);
    }
    return assignPrivileges($role, $role_privileges);
}

//assign a privilege to the specified role (keep any other assignements)
function assign_role_privilege($role, $privilege){
    assign_or_unassign_role_privilege($role, $privilege, TRUE);
}

//unassign a privilege from the specified role (keep any other assignements)
function unassign_role_privilege($role, $privilege){
    assign_or_unassign_role_privilege($role, $privilege, FALSE);
}

$status = '';
if (isset($_POST['action'])) {
    $action = strtolower($_POST['action']);
}
else {
    $action = NULL;
}
switch ($action) {
    case 'create user':
        $status = create_user($_POST['user'], $_POST['password']);
        break;
    case 'delete selected users':
        $status = delete_users($_POST['names']);
        break;
    case 'create role':
        $status = create_role($_POST['role']);
        break;
    case 'delete selected roles':
        $status = delete_roles($_POST['names']);
        break;
    case 'assign':
        switch ($_GET['view']) {
            case 'UsersRoles':
                assign_user_role($_POST['item'], $_POST['related_item']);
                break;
            case 'RolesUsers':
                assign_role_user($_POST['item'], $_POST['related_item']);
                break;
            case 'RolePrivileges':
                assign_role_privilege($_POST['item'], $_POST['related_item']);
                break;
        }
        break;
    case 'unassign':
        switch ($_GET['view']) {
            case 'UsersRoles':
                unassign_user_role($_POST['item'], $_POST['related_item']);
                break;
            case 'RolesUsers':
                unassign_role_user($_POST['item'], $_POST['related_item']);
                break;
            case 'RolePrivileges':
                unassign_role_privilege($_POST['item'], $_POST['related_item']);
                break;
        }
        break;
//our addition 
    case 'create users':
        {
            $row = 1;
            if (($handle = fopen($filenameCSV, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $num = count($data);
                    echo "<p> $num fields in line $row: <br /></p>\n";
                    $row++;

                    /*'echo 'user is '.$data[0]."\n";
                    echo 'pass is '.$data[1]."\n";
                    '*/
                    $status = create_user($data[0], $data[1]);
                    assign_user_role($data[0], $roleAssign);
                    echo 'user created for '.$data[0]." with the role ".$roleAssign;
                    /*for ($c=0; $c < $num; $c++) {
                        echo $data[$c] . "<br />\n";
                    }*/
                }
                fclose($handle);
            }


            // $status = create_user($_POST['user'], $_POST['password']);


        }
        break;
}

?>
<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>PHP Pentaho Admin Client</title>

    <!--Import materialize.css-->
    <link type="text/css" rel="stylesheet" href="sass/materialize.scss"  media="screen,projection"/>

    <!--Let browser know website is optimized for mobile-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

</head>
<body>
<?php
if ($status !== '') {
    if ($status === 200) {
        echo('Success!');
    }
    else {
        echo('Error: '.$status);
    }
}
?>
<?php
if (isset($_GET['view'])) {
    $view = $_GET['view'];
    $item = $_GET['item'];
    ?>
    <form name="assignment" method="POST" action="?view=<?php echo($view)?>&item=<?php echo(urlencode($item))?>">
        <input type="hidden" name="action"/>
        <input type="hidden" name="related_item"/>
        <input type="hidden" name="item" value="<?php echo($item)?>"/>
    </form>
    <script type="text/javascript">
        function changeAssignment(checkbox) {
            var form = document.forms["assignment"];
            form.elements["action"].value = checkbox.checked ? "assign" : "unassign";
            form.elements["related_item"].value = checkbox.name;
            form.submit();
        }
    </script>
    <?php
}
else {
    $view = NULL;
}

switch ($view) {
case 'UsersRoles':
    $roles = get_user_roles($item);
    $assigned_roles = array();
    foreach ($roles as $role) {
        array_push($assigned_roles, ''.$role);
    }
    $roles = get_roles();
foreach ($roles as $role) {
    $checked = in_array(''.$role, $assigned_roles);
    ?>
    <div>
        <input
                onchange="changeAssignment(this)"
                name="<?php echo($role) ?>"
                type="checkbox"
            <?php echo ($checked ? 'checked="true"' : '')?>
        />
        <?php echo($role) ?>
    </div>
<?php
}
break;
case 'RolesUsers':
$users = get_role_members($item);
$assigned_users = array();
foreach ($users as $user) {
    array_push($assigned_users, ''.$user);
}
$users = get_users();
foreach ($users as $user) {
$checked = in_array(''.$user, $assigned_users);
?>
    <div>
        <input
                onchange="changeAssignment(this)"
                name="<?php echo($user) ?>"
                type="checkbox"
            <?php echo ($checked ? 'checked="true"' : '')?>
        />
        <?php echo($user) ?>
    </div>
<?php
}
break;
case 'RolePrivileges':
$privileges = get_privileges();
$assignment = get_role_privilege_assignment($privileges, $item);

if ($assignment) {
    $immutable = ''.$assignment->immutable == 'true';
    $logicalRoles = get_role_privileges($assignment);
}
else {
    $immutable = FALSE;
    $logicalRoles = array();
}

foreach ($privileges->localizedRoleNames as $role) {
$checked = in_array(''.$role->roleName, $logicalRoles);
?>
    <div>
        <input
                onchange="changeAssignment(this)"
                name="<?php echo($role->roleName); ?>"
                type="checkbox"
            <?php echo ($checked ? 'checked="true"' : ''); ?>
            <?php echo ($immutable ? 'disabled="true"' : ''); ?>
        />
        <?php echo($role->localizedName); ?>
    </div>
<?php
}
break;
default:
?>
    <script type="text/javascript">

        function getSelectedOptionValues(id){
            var list = document.getElementById(id);
            var options = list.options, n = options.length, option, i;
            var optionValues = [];
            for (i = 0; i < n; i++){
                option = options[i];
                if (option.selected) {
                    optionValues.push(option.value);
                }
            }
            return optionValues;
        }

        function deleteUsersOrRoles(type){
            var form = document.forms["delete" + type];
            var items = getSelectedOptionValues(type);
            form.elements["names"].value = items.join("\t");
            form.submit();
        }

        function selectionChanged(selectElement){
            var view, id = selectElement.id;
            var value = getSelectedOptionValues(id);
            var frame, url, item;
            switch (id) {
                case "Users":
                    view = ["UsersRoles"];
                    frame = ["RolesFrame"];
                    break;
                case "Roles":
                    view = ["RolesUsers", "RolePrivileges"];
                    frame = ["UsersFrame", "PrivilegesFrame"];
                    break;
            }
            var i, n = frame.length;
            for (i = 0; i < n; i++) {
                if (value.length === 1) {
                    item = value[0];
                    url = "?view=" + view[i] + "&item=" + item;
                }
                else {
                    url = "about://blank";
                }
                document.getElementById(frame[i]).src = url;
            }
        }

    </script>
    <table >
        <tr>
            <th>User:</th>
            <th>Role:</th>
        </tr>
        <tr>
            <td>
                <form method="POST">
                    <table>
                        <tr>
                            <td>Username:</td>
                            <td><input type="text" name="user" /></td>
                        </tr>
                        <tr>
                            <td>Password:</td>
                            <td><input type="password" name="password" /></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <input type="submit" name="action" value="Create User"/>
                            </td>
                        </tr>
                    </table>
                </form>
            </td>
            <td>
                <form method="POST">
                    <table>
                        <tr>
                            <td>Rolename:</td>
                            <td><input type="text" name="role" /></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <input type="submit" name="action" value="Create Role"/>
                            </td>
                        </tr>
                    </table>
                </form>
            </td>
        </tr>
        <tr>
            <th>Existing Users:</th>
            <th>Existing Roles:</th>
        </tr>
        <tr>
            <td>
                <select multiple="true" id="Users" onchange="selectionChanged(this)">
                    <?php
                    $users = get_users();
                    foreach ($users as $user) {
                        ?>
                        <option><?php echo($user); ?></option>
                        <?php
                    }
                    ?>
                </select>
            </td>
            <td>
                <select multiple="true" id="Roles" onchange="selectionChanged(this)">
                    <?php

                    $roles = get_roles();
                    foreach ($roles as $role) {
                        ?>
                        <option><?php echo($role); ?></option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>
                <form method="POST" name="deleteUsers">
                    <input type="hidden" name="names"/>
                    <input type="hidden" name="action" value="Delete selected users"/>
                    <input type="button" value="Delete selected users" onclick="deleteUsersOrRoles('Users')"/>
                </form>
            </td>
            <td>
                <form method="POST" name="deleteRoles">
                    <input type="hidden" name="names"/>
                    <input type="hidden" name="action" value="Delete selected roles"/>
                    <input type="button" value="Delete selected roles" onclick="deleteUsersOrRoles('Roles')"/>
                </form>
            </td>
        </tr>
        <tr>
            <th>
                User roles:
            </th>
            <th>
                Role members:
            </th>
            <th>
                Privileges (logical roles):
            </th>
        </tr>
        <tr>
            <td>
                <iframe style="border-style:none" border="0" id="RolesFrame"></iframe>
            </td>
            <td>
                <iframe style="border-style:none" border="0" id="UsersFrame"></iframe>
            </td>
            <td>
                <iframe style="border-style:none" border="0" id="PrivilegesFrame"></iframe>
            </td>
        </tr>
    </table>
    <h5>Click on this button to create users from csv and assign roles:</h5>
    <form method="post">

        <input type="submit" name="action" value="Create Users"/>
    </form>
<?php
}
?>
</body>
</html>
