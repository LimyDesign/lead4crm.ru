<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/{_locale}", defaults={"_locale" = "ru"})
     * @Cache(maxage="20", public=true)
     */
    public function indexAction(Request $request)
    {
        if (@$ver_serialize = $this->get('cache')->fetch('ver')) {
            $ver = unserialize($ver_serialize);
        } else {
            $buzz = $this->container->get('buzz');
            $response = $buzz->get('https://api.github.com/repos/LimyDesign/lead4crm.ru/git/refs/tags', [
                'User-Agent: Mozilla/5.0 (compatible; Lead4CRM/1.0; +https://www.lead4crm.ru/robots)'
            ]);
            $content = json_decode($response->getContent());
            list($ref, $tag, $ver) = explode('/', $content[count($content) - 1]->ref);
            $this->get('cache')->save('ver', serialize($ver));
        }
        return $this->render('default/index.html.twig', [
            'ver' => $ver,
        ]);
    }

    /**
     * @Route("/about-project", defaults={"_locale" = "ru"})
     * @Route("/{_locale}/about-project", defaults={"_locale" = "ru"})
     *
     * @return Response
     */
    public function aboutProjectAction() {
        return $this->render('default/about-project.html.twig');
    }

    /**
     * @Route("/cabinet", defaults={"_locale" = "ru"})
     * @Route("/{_locale}/cabinet", defaults={"_locale" = "ru"})
     */
    public function cabinetAction() {
        return $this->render('cabinet/index.html.twig');
    }

    /**
     * @Route("/login", defaults={"_locale" = "ru"})
     * @Route("/{_locale}/login", defaults={"_locale" = "ru"})
     */
    public function loginAction() {
        $authUtils = $this->get('security.authentication_utils');

        // get the login error if there is one
        $error = $authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authUtils->getLastUsername();

        return $this->render('cabinet/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}
