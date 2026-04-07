<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>HabitOS - Admin Panel</title>
        <?php include 'system/header.php' ?>
        
        <!-- Bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

        <!-- Other CSS -->
        <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
        <link rel="stylesheet" href="stylesheets/default.css">
    </head>
    <body>
        <div class="w3-container">
            <h1>Create a new account</h1>
            <form name="register" action="registerAction.php" method="get">
                <div class="mb-3">
                    <label for="signUpUsername" class="form-label">Username</label>
                    <input type="text" name="user" class="form-control" size="40" id="signUpUsername" aria-describedby="usernameHelp" required>
                    <div id="usernameHelp" class="form-text">Maximum of 40 characters</div>
                </div>
                <div class="mb-3">
                    <label for="signUpEmail" class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" id="signUpEmail" aria-describedby="emailHelp" required>
                    <div id="emailHelp" class="form-text"></div>
                </div>
                <div class="mb-3">
                    <label for="signUpPassword" class="form-label">Password</label>
                    <input type="password" name="pass" class="form-control" id="signUpPassword" required>
                </div>
                <div class="mb-3">
                    <label for="signUpWhoAmI" class="form-label">Who am I?</label>
                    <select class="form-select" name="whoAmI" id="signUpWhoAmI" aria-label="Select one">
                        <option disabled selected value>Select one</option>
                        <option value="1">Gamer</option>
                        <option value="2">Journalist</option>
                        <option value="3">Esports Member</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="newsletter" value="yes" id="signUpNewsletter">
                        <label class="form-check-label" for="signUpNewsletter">
                            Sign up for our newsletter.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="terms" id="signUpTerms" required>
                        <label class="form-check-label" for="signUpTerms">
                            I agree to the terms of service.
                        </label>
                    </div>
                </div>
                <input type="submit" class="btn btn-outline-success" value="Submit" />
                <input type="reset" class="btn btn-outline-danger" value="Reset" />
            </form>
        </div>
        <?php include 'system/footer.php' ?>
    </body>
</html>
