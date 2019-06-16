import React from 'react'
import { Link } from 'react-scroll'
import styled from 'styled-components'

import { Navigation, getBackgroundColor } from 'semantic-styled-ui'

// import GImage from 'gatsby-image'

const S = {}

S.Nav = styled.nav`
  position: fixed;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  width: 100%;
  top: 0;
  align-items: center;
  z-index: 9;
  ${getBackgroundColor('primary')};

  div:first-child {
    justify-self: end;
  }

  div:last-child {
    justify-self: start;
  }
`

S.NavItem = styled(Navigation.Item)`
  color: #fff;
  text-shadow: 0px 2px 4px black;
  padding: 5px;

  &:hover {
    color: rgba(#fff, 0.75);
  }
`

const Logo = styled('svg')`
  &&& {
    vertical-align: middle;
    padding: 10px;
    width: 50px !important;
    height: 50px !important;
  }
`

const pages = ['about', 'projects', 'experience', 'contact']

const Header = () => (
  <S.Nav>
    <div>
      {pages.slice(0, pages.length / 2).map((page, i) => (
        <S.NavItem
          forwardedAs={Link}
          key={page}
          link={`#${page}`}
          offset={-60}
          tabIndex='0'
        >
          {page}
        </S.NavItem>
      ))}
    </div>
    <Navigation.Logo link='#title'>
      <Logo
        xmlns='http://www.w3.org/2000/svg'
        viewBox='0 0 934 934'
      >
        <title>ZH Logo V4</title>
        <path className='line' d='M189.51 244.81l299.37-.75-299.46 299.46L745 544M487.53 371.47v343.59M694.34 371.34v343.85' />
        <circle className='outline' cx='467' cy='467' r='437' />
      </Logo>
    </Navigation.Logo>
    <div>
      {pages.slice(pages.length / 2).map((page, i) => (
        <S.NavItem
          forwardedAs={Link}
          key={page}
          link={`#${page}`}
          offset={-60}
          tabIndex='0'
        >
          {page}
        </S.NavItem>
      ))}
    </div>
  </S.Nav>
)

export default Header
