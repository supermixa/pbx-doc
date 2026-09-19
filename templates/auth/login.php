<?php
use App\Core\Csrf;
use App\Core\View;
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card mt-5">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center">☎ PBX Doc</h4>
                <form method="post" action="/login">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="redirect" value="<?= View::e($redirect ?? '/') ?>">

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Войти</button>
                </form>
            </div>
        </div>
    </div>
</div>
