<link rel="stylesheet" type="text/css" href="{THEME}/dle_ipb/css/dle_ipb.css">
<div class="panel panel-default">
<!-- Last topic per forum -->
  <div class="panel-heading">Последние темы с форума</div>
  <table class="table">
    <thead>
      <tr>
        <th>Название</th>
        <th>Автор</th>
        <th>Форум</th>
        <th>Просмотров</th>
        <th>Ответов</th>
      </tr>
    </thead>
    <tbody>
      [row]
      <tr>
        <td width='40%' valign='top' style="padding-left:7px;">
          <a data-toggle='popover' title="Сообщение в теме {shot_name_post}" data-content="{p_content}"  title='{full_name} ({user} : {date})' href='{post_url}'>{shot_name_post}</a>
        </td>
        <td width="20%" style='padding-right:5px;'>
          <a href="{user_url}" >{user}</a>
        </td>
        <td width='20%' valign='top' align='left' style="padding-left:7px;">
          <a title='{forum}' href='{forum_url}'>{forum}</a>
        </td>
        <td width="10%">{view_count}</td>
        <td width="10%">{reply_count}</td>
      </tr>
      [/row]
    </tbody>
  </table>
</div>