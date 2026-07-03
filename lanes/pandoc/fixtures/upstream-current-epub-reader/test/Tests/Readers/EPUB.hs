featuresBag :: [(String, String, Int)]
featuresBag = [("img/check.gif","image/gif",1340)
              ,("img/check.jpg","image/jpeg",2661)
              ,("img/check.png","image/png",2815)
              ,("img/multiscripts_and_greek_alphabet.png","image/png",10060)
              ]

epub3CoverBag :: [(String, String, Int)]
epub3CoverBag = [("wasteland-cover.jpg","image/jpeg", 16586)]

epub3NoCoverBag :: [(String, String, Int)]
epub3NoCoverBag = [("img/check.gif","image/gif",1340)
                  ,("img/check.jpg","image/jpeg",2661)
                  ,("img/check.png","image/png",2815)
                  ]

epub2PictureBag :: [(String, String, Int)]
epub2PictureBag = [("image/image.jpg","image/jpeg",9713)]

epub2CoverBag :: [(String, String, Int)]
epub2CoverBag = [("image/cover.jpg","image/jpeg",9713)]

epub2NoCoverBag :: [(String, String, Int)]
epub2NoCoverBag = []

tests :: [TestTree]
tests =
  [ testGroup "EPUB Mediabag"
    [ testCase "features bag"
      (testMediaBag "epub/img.epub" featuresBag),
      testCase "EPUB3 cover bag"
      (testMediaBag "epub/wasteland.epub" epub3CoverBag),
      testCase "EPUB3 no cover bag"
      (testMediaBag "epub/img_no_cover.epub" epub3NoCoverBag),
      testCase "EPUB2 picture bag"
      (testMediaBag "epub/epub2_picture.epub" epub2PictureBag),
      testCase "EPUB2 cover bag"
      (testMediaBag "epub/epub2_cover.epub" epub2CoverBag),
      testCase "EPUB2 no cover bag"
      (testMediaBag "epub/epub2_no_cover.epub" epub2NoCoverBag)
    ]
  ]
