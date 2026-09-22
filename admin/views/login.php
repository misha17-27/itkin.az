<?php
/** Giriş səhifəsi / login */
admin_head('Giriş');
?>
<div class="login">
	<form class="login__box" method="post" action="<?= e(admin_url()) ?>">
		<?= admin_token_field() ?>
		<img class="login__logo" src="<?= asset('uploads/2023/11/fav.png') ?>" alt="">
		<h1>İdarə paneli</h1>
		<p>“Qarabağ İtkin Ailələri” İctimai Birliyi</p>

<?php if ($error !== ''): ?>
		<div class="errors"><?= e($error) ?></div>
<?php endif; ?>

		<div class="field">
			<label for="f-user">İstifadəçi</label>
			<input class="input" type="text" id="f-user" name="user" autocomplete="username"
			       value="<?= e(post_str('user')) ?>" required autofocus>
		</div>
		<div class="field">
			<label for="f-password">Şifrə</label>
			<input class="input" type="password" id="f-password" name="password" autocomplete="current-password" required>
		</div>
		<div class="actions">
			<button class="btn btn--primary" type="submit" style="width:100%;justify-content:center">Daxil ol</button>
		</div>
	</form>
</div>
</body>
</html>
