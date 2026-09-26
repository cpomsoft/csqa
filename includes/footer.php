        </main>
    </div>

    <footer class="csqa-footer">
        <div>&copy; <a href="https://www.ucl.ac.uk">UCL</a> 2010&ndash;<?= gmdate('Y') ?></div>
        <div>Please refer any problems to <a href="mailto:<?= h(CSQA_CONTACT_EMAIL) ?>"><?= h(CSQA_CONTACT_EMAIL) ?></a></div>
    </footer>
</div>
<script src="<?= h(CSQA_BOOTSTRAP_JS) ?>"></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
