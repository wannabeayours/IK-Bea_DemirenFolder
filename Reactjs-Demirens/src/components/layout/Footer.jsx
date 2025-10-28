import { MessageCircleHeart, Phone, PinIcon } from 'lucide-react'
import React from 'react'
import { useLocation, useNavigate } from 'react-router-dom'

function Footer() {
  const navigate = useNavigate()
  const location = useLocation()

  const scrollToSection = (id) => {
    const doScroll = () => {
      const el = document.getElementById(id)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }
    }

    if (location.pathname !== '/') {
      navigate('/')
      setTimeout(doScroll, 300)
    } else {
      doScroll()
    }
  }

  return (
    <footer className="py-8 bg-blue-900 rounded-t-3xl text-white ">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 ">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
          {/* QUICK LINKS */}
          <div>
            <h3 className="text-lg font-semibold mb-4 uppercase tracking-wider">QUICK LINKS</h3>
            <ul className="space-y-3">
              <button
                type="button"
                onClick={() => scrollToSection('about')}
                className="text-left hover:text-blue-300 transition-colors duration-200"
              >
                About
              </button><br />

              <button
                type="button"
                onClick={() => scrollToSection('rooms')}
                className="text-left hover:text-blue-300 transition-colors duration-200"
              >
                Rooms
              </button><br />

            </ul>
          </div>

          {/* CONTACT US */}
          <div>
            <h3 className="text-lg font-semibold mb-4 uppercase tracking-wider">CONTACT US</h3>
            <ul className="space-y-3">
              <div className="flex items-start">
                <span className="mr-3 mt-1">
                  <Phone />
                </span>
                <span>0906 231 4236</span>
              </div>
              <div className="flex items-start">
                <span className="mr-3 mt-1">
                  <MessageCircleHeart />
                </span>
                <span>demirenhotel@yahoo.com.ph</span>
              </div>
              <div className="flex items-start">
                <span className="mr-3 mt-1">
                  <PinIcon />
                </span>
                <span>Tiano Kalambaguhan Street, Brgy 14, Cagayan de Oro, Philippines </span>
              </div>
            </ul>
          </div>

        </div>

        {/* COPYRIGHT */}
        <div className="border-t border-white pt-6 text-center text-sm">
          <p>Copyright © 2025 by Demiren Hotel and Restaurant. All rights reserved.</p>
        </div>
      </div>

    </footer>
  )
}


export default Footer